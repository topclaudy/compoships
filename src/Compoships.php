<?php

namespace Awobaz\Compoships;

use Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships;
use Awobaz\Compoships\Database\Grammar\MariaDbGrammar;
use Awobaz\Compoships\Database\Grammar\MySqlGrammar;
use Awobaz\Compoships\Database\Grammar\PostgresGrammar;
use Awobaz\Compoships\Database\Grammar\SQLiteGrammar;
use Awobaz\Compoships\Database\Grammar\SqlServerGrammar;
use Awobaz\Compoships\Database\Query\Builder as QueryBuilder;
use Awobaz\Compoships\Exceptions\InvalidUsageException;

trait Compoships
{
    use HasRelationships;

    public function getAttribute($key)
    {
        if (is_array($key)) { //Check for multi-columns relationship
            return array_map(fn ($k) => parent::getAttribute($k), $key);
        }

        return parent::getAttribute($key);
    }

    public function qualifyColumn($column)
    {
        if (is_array($column)) {
            return array_map(fn ($c) => parent::qualifyColumn($c), $column);
        }

        return parent::qualifyColumn($column);
    }

    /**
     * Composite-key columns to AND into the save/select WHERE clause,
     * excluding the scalar primary key (already added by parent).
     *
     * Consumers opt in by declaring `protected $compositeKey = [...]`
     * on the consuming model. The property is intentionally NOT declared
     * on the trait so consumers can pick their own default value without
     * triggering PHP's "trait property re-declaration" incompatibility.
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     *
     * @return array<int, string>
     */
    protected function getAdditionalKeyNames()
    {
        if (!property_exists($this, 'compositeKey') || empty($this->compositeKey)) {
            return [];
        }

        if (!in_array($this->getKeyName(), $this->compositeKey, true)) {
            throw new InvalidUsageException(sprintf(
                'Model %s declares $compositeKey but does not include the scalar primary key "%s". Add it to $compositeKey or remove $compositeKey entirely.',
                static::class,
                $this->getKeyName()
            ));
        }

        return array_values(array_diff($this->compositeKey, [$this->getKeyName()]));
    }

    /**
     * Composite-key column values keyed by column name, for use in
     * queue serialization identity blobs. Returns null when the
     * consumer has not opted into composite-key handling.
     *
     * Value source is the current in-memory attribute value (NOT raw
     * original). This matches Laravel's stock getQueueableId semantic
     * that queue serialization captures the model's current identity.
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     *
     * @return array<string, mixed>|null
     */
    public function getCompositeKeyValues()
    {
        if (!property_exists($this, 'compositeKey') || empty($this->compositeKey)) {
            return null;
        }

        if (!in_array($this->getKeyName(), $this->compositeKey, true)) {
            throw new InvalidUsageException(sprintf(
                'Model %s declares $compositeKey but does not include the scalar primary key "%s". Add it to $compositeKey or remove $compositeKey entirely.',
                static::class,
                $this->getKeyName()
            ));
        }

        $values = [];

        foreach ($this->compositeKey as $column) {
            $values[$column] = $this->getAttribute($column);
        }

        return $values;
    }

    /**
     * Override getQueueableId to return a JSON-encoded composite key
     * tuple when $compositeKey is declared. The string form keeps the
     * id out of Laravel's restoreCollection branch, which fires on
     * is_array($id) and is intended for EloquentCollection round-trip.
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     *
     * @return mixed
     */
    public function getQueueableId()
    {
        $values = $this->getCompositeKeyValues();

        if ($values === null) {
            return parent::getQueueableId();
        }

        return json_encode($values);
    }

    /**
     * Override newQueryForRestoration to decode a JSON-encoded composite
     * id and build a query that ANDs equality (or whereNull for null)
     * predicates for every composite column. Non-composite ids and
     * shape-mismatched payloads delegate to the parent path.
     *
     * Collection round-trip is not supported. Laravel's restoreCollection
     * re-keys loaded models by scalar getKey() and looks up by the
     * original queued ids, which for composite-keyed models are our
     * JSON-encoded composite strings. The keys never match and the
     * restored collection is empty. Fixing this requires a Laravel
     * change to restoreCollection (overriding getKey() to return JSON
     * is not viable because getKey() is used by find, route binding,
     * associate, and many other internal paths).
     *
     * @param mixed $ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQueryForRestoration($ids)
    {
        if (is_string($ids) && property_exists($this, 'compositeKey') && !empty($this->compositeKey)) {
            $decoded = json_decode($ids, true);

            if (is_array($decoded)
                && count($decoded) === count($this->compositeKey)
                && empty(array_diff(array_keys($decoded), $this->compositeKey))) {
                $query = $this->newQueryWithoutScopes();

                foreach ($decoded as $column => $value) {
                    $value === null
                        ? $query->whereNull($column)
                        : $query->where($column, '=', $value);
                }

                return $query;
            }
        }

        return parent::newQueryForRestoration($ids);
    }

    /**
     * Set the keys for a save UPDATE / DELETE query, including any
     * additional composite-key columns declared via $compositeKey.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $query = parent::setKeysForSaveQuery($query);

        foreach ($this->getAdditionalKeyNames() as $column) {
            $value = array_key_exists($column, $this->original)
                ? $this->original[$column]
                : $this->getAttribute($column);

            $value === null
                ? $query->whereNull($column)
                : $query->where($column, '=', $value);
        }

        return $query;
    }

    /**
     * Set the keys for a SELECT query used by fresh() / refresh(),
     * including any additional composite-key columns declared via
     * $compositeKey.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSelectQuery($query)
    {
        $query = parent::setKeysForSelectQuery($query);

        foreach ($this->getAdditionalKeyNames() as $column) {
            $value = array_key_exists($column, $this->original)
                ? $this->original[$column]
                : $this->getAttribute($column);

            $value === null
                ? $query->whereNull($column)
                : $query->where($column, '=', $value);
        }

        return $query;
    }

    /**
     * Configure Eloquent to use Compoships Query Builder.
     *
     * @return \Awobaz\Compoships\Database\Query\Builder|static
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        $grammar = match ($connection->getDriverName()) {
            'mysql'   => new MySqlGrammar($connection),
            'pgsql'   => new PostgresGrammar($connection),
            'sqlite'  => new SQLiteGrammar($connection),
            'sqlsrv'  => new SqlServerGrammar($connection),
            'mariadb' => new MariaDbGrammar($connection),
            default   => $connection->getQueryGrammar(),
        };

        if (method_exists($grammar, 'setConnection')) {
            $grammar->setConnection($connection);
        }

        if (method_exists($connection, 'withTablePrefix')) {
            $grammar = $connection->withTablePrefix($grammar);
        }

        return new QueryBuilder($connection, $grammar, $connection->getPostProcessor());
    }
}
