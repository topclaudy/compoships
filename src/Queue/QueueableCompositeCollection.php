<?php

namespace Awobaz\Compoships\Queue;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;

/**
 * Round-trip wrapper for collections of composite-keyed Compoships
 * models through queue serialization. Sidesteps Laravel's
 * restoreCollection limitation by capturing composite-key tuples
 * at wrap time and rebuilding the collection via composite-aware
 * query at restore time.
 *
 * Usage:
 *
 *     class MyJob {
 *         use SerializesModels;
 *         public QueueableCompositeCollection $users;
 *         public function __construct(EloquentCollection $users) {
 *             $this->users = QueueableCompositeCollection::for($users);
 *         }
 *         public function handle() {
 *             $users = $this->users->restore();
 *             // ...
 *         }
 *     }
 */
class QueueableCompositeCollection
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model>|null */
    protected $modelClass = null;

    /** @var array<int, array<string, mixed>> */
    protected $tuples = [];

    /** @var array<int, string> */
    protected $relations = [];

    /** @var string|null */
    protected $connectionName = null;

    /**
     * Build a bag from a collection of composite-keyed models.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     *
     * @throws \LogicException                                     When the collection contains models of more than one class.
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException When the models do not use the trait or declare no $compositeKey, or when a $compositeKey omits the scalar primary key.
     *
     * @return self
     */
    public static function for(EloquentCollection $models)
    {
        $bag = new self();

        if ($models->isEmpty()) {
            return $bag;
        }

        $first = $models->first();
        $class = get_class($first);

        $models->each(function ($model) use ($class) {
            if (get_class($model) !== $class) {
                throw new LogicException(sprintf(
                    'QueueableCompositeCollection does not support collections containing multiple model types. Got %s alongside %s.',
                    get_class($model),
                    $class
                ));
            }
        });

        if (!method_exists($first, 'getCompositeKeyValues')) {
            throw new InvalidUsageException(sprintf(
                'QueueableCompositeCollection requires models using the Compoships trait with a $compositeKey; %s does not use the trait.',
                $class
            ));
        }

        $tuples = $models->values()->map(fn ($model) => $model->getCompositeKeyValues())->all();

        if ($tuples[0] === null) {
            throw new InvalidUsageException(sprintf(
                'QueueableCompositeCollection requires models declaring a $compositeKey; %s declares none.',
                $class
            ));
        }

        $bag->modelClass = $class;
        $bag->relations = $first->getQueueableRelations();
        $bag->connectionName = $first->getConnectionName();
        $bag->tuples = $tuples;

        return $bag;
    }

    /**
     * Rebuild the collection from the captured composite-key tuples.
     * Original order is preserved. Models that no longer exist in the
     * database are silently dropped (matches SerializesModels behavior).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function restore()
    {
        if (empty($this->tuples) || $this->modelClass === null) {
            return $this->modelClass === null
                ? new EloquentCollection()
                : (new $this->modelClass())->newCollection();
        }

        $class = $this->modelClass;
        /** @var \Illuminate\Database\Eloquent\Model $instance */
        $instance = new $class();

        if ($this->connectionName !== null) {
            $instance->setConnection($this->connectionName);
        }

        // Match SerializesModels::restoreCollection(): no global scopes (so a
        // member soft-deleted after queueing is still restored) and the write
        // connection (so a member created just before queueing is visible).
        $query = $instance->newQueryWithoutScopes()->useWritePdo()->where(function ($outer) {
            foreach ($this->tuples as $tuple) {
                $outer->orWhere(function ($inner) use ($tuple) {
                    foreach ($tuple as $column => $value) {
                        $value === null
                            ? $inner->whereNull($column)
                            : $inner->where($column, '=', $value);
                    }
                });
            }
        });

        if (!empty($this->relations)) {
            $query->with($this->relations);
        }

        return $this->orderedByOriginalTuples($query->get());
    }

    /**
     * Build an output collection ordered to match the original tuple
     * order, using a JSON-encoded tuple key to look up each loaded model.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $loaded
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    protected function orderedByOriginalTuples(EloquentCollection $loaded)
    {
        $columns = array_keys($this->tuples[0]);

        $keyed = [];

        foreach ($loaded as $model) {
            $modelTuple = [];

            foreach ($columns as $column) {
                $modelTuple[$column] = $model->getAttribute($column);
            }

            $keyed[$this->canonicalKey($modelTuple)] = $model;
        }

        $ordered = (new $this->modelClass())->newCollection();

        foreach ($this->tuples as $tuple) {
            $key = $this->canonicalKey($tuple);

            if (isset($keyed[$key])) {
                $ordered->push($keyed[$key]);
            }
        }

        return $ordered;
    }

    /**
     * Canonical JSON encoding of a composite-key tuple, used as a map
     * key for ordering on restore. Scalar values are normalized to
     * string form before encoding so that PHP-side type variance
     * (e.g., int 1 in memory vs. string "1" returned by PDO for an
     * uncasted column) does not cause the lookup to miss.
     *
     * @param array<string, mixed> $tuple
     *
     * @return string
     */
    protected function canonicalKey(array $tuple)
    {
        $normalized = [];

        foreach ($tuple as $column => $value) {
            $normalized[$column] = $this->normalizeValueForKey($value);
        }

        return json_encode($normalized);
    }

    /**
     * Coerce a tuple value into a canonical form for use as a lookup
     * map key. Null is preserved as null (distinct from any scalar).
     * BackedEnum values are reduced to their backing scalar as string.
     * Booleans are normalized to "1"/"0". Other scalars (int, float,
     * string) are stringified. Non-scalar non-enum values pass through
     * unchanged so that json_encode's default behavior applies.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function normalizeValueForKey($value)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        return $value;
    }
}
