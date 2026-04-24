<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Awobaz\Compoships\Concerns\ResolvesBackedEnumValues;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as BaseBelongsToMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TPivotModel of \Illuminate\Database\Eloquent\Relations\Pivot
 *
 * @extends BaseBelongsToMany<TRelatedModel, TDeclaringModel, TPivotModel>
 */
class BelongsToMany extends BaseBelongsToMany
{
    use ResolvesBackedEnumValues;

    /**
     * Whether this relation is composite on EITHER side. Methods that touch both
     * sides of the pivot record (e.g. `baseAttachRecord`) must not delegate to
     * Laravel's stock implementation when only one side is composite, otherwise
     * the stock code chokes on the array side it doesn't expect.
     *
     * Used as the delegation guard everywhere a method's body needs to handle
     * one of the four (scalar/composite) x (foreign/related) quadrants:
     *   (scalar, scalar)     -> safe to delegate to parent
     *   (composite, scalar)  -> custom handling required (composite foreign side)
     *   (scalar, composite)  -> custom handling required (composite related side)
     *   (composite, composite) -> custom handling required (both)
     */
    protected function isComposite(): bool
    {
        return is_array($this->relatedPivotKey) || is_array($this->foreignPivotKey);
    }

    /**
     * Set the join clause for the relation query.
     *
     * @param \Illuminate\Database\Eloquent\Builder|null $query
     *
     * @return $this
     */
    protected function performJoin($query = null)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::performJoin($query);
        }

        $query = $query ?: $this->query;

        $query->join($this->table, function ($join) {
            foreach ($this->relatedKey as $index => $key) {
                $join->on(
                    $this->related->qualifyColumn($key),
                    '=',
                    $this->qualifyPivotColumn($this->relatedPivotKey[$index])
                );
            }
        });

        return $this;
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function addWhereConstraints()
    {
        if (!is_array($this->foreignPivotKey)) {
            return parent::addWhereConstraints();
        }

        foreach ($this->foreignPivotKey as $index => $key) {
            $this->query->where(
                $this->qualifyPivotColumn($key),
                '=',
                $this->resolveBackedEnumValue($this->parent->{$this->parentKey[$index]})
            );
        }

        return $this;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        if (!is_array($this->parentKey)) {
            parent::addEagerConstraints($models);

            return;
        }

        $whereIn = $this->whereInMethod($this->parent, $this->parentKey);
        $modelKeys = $this->getCompositeKeys($models, $this->parentKey);
        $qualifiedKeys = $this->qualifyPivotColumn($this->foreignPivotKey);

        $query = $this->getRelationQuery() ?? $this->query;
        $query->{$whereIn}($qualifiedKeys, $modelKeys);

        if ($modelKeys === []) {
            $this->eagerKeysWereEmpty = true;
        }
    }

    /**
     * Get the name of the "where in" method for eager loading.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string|array                        $key
     *
     * @return string
     */
    protected function whereInMethod(Model $model, $key)
    {
        if (!is_array($key)) {
            return parent::whereInMethod($model, $key);
        }

        $where = collect($key)->filter(fn ($key) => $model->getKeyName() === last(explode('.', $key))
            && in_array($model->getKeyType(), ['int', 'integer']));

        return $where->count() === count($key) ? 'whereIntegerInRaw' : 'whereIn';
    }

    /**
     * Get composite key tuples from an array of models.
     *
     * @param array $models
     * @param array $keys
     *
     * @return array
     */
    protected function getCompositeKeys(array $models, array $keys): array
    {
        $result = [];

        foreach ($models as $model) {
            $result[] = array_map(fn ($k) => $this->resolveBackedEnumValue($model->{$k}), $keys);
        }

        sort($result);

        return array_map('unserialize', array_unique(array_map('serialize', $result)));
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array                                    $models
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string                                   $relation
     *
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        if (!is_array($this->parentKey)) {
            return parent::match($models, $results, $relation);
        }

        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $this->buildDictionaryKey($model, $this->parentKey);

            if (isset($dictionary[$key])) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     *
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        if (!is_array($this->foreignPivotKey)) {
            return parent::buildDictionary($results);
        }

        $dictionary = [];

        foreach ($results as $result) {
            $key = $this->buildDictionaryKey($result->{$this->accessor}, $this->foreignPivotKey);
            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        if (!is_array($this->parentKey)) {
            return parent::getResults();
        }

        foreach ($this->parentKey as $key) {
            if (!is_null($this->parent->{$key})) {
                return $this->get();
            }
        }

        return $this->related->newCollection();
    }

    /**
     * Qualify the given column name by the pivot table.
     *
     * @param string|array $column
     *
     * @return string|array
     */
    public function qualifyPivotColumn($column)
    {
        if (is_array($column)) {
            return array_map(fn ($col) => parent::qualifyPivotColumn($col), $column);
        }

        return parent::qualifyPivotColumn($column);
    }

    /**
     * Get the pivot columns for the relation.
     *
     * @return array
     */
    protected function aliasedPivotColumns()
    {
        $foreignKeys = is_array($this->foreignPivotKey)
            ? $this->foreignPivotKey
            : [$this->foreignPivotKey];

        $relatedKeys = is_array($this->relatedPivotKey)
            ? $this->relatedPivotKey
            : [$this->relatedPivotKey];

        $columns = array_merge($foreignKeys, $relatedKeys, $this->pivotColumns);

        return collect($columns)
            ->map(fn ($column) => $this->qualifyPivotColumn($column).' as pivot_'.$column)
            ->unique()
            ->all();
    }

    /**
     * Get the fully-qualified parent key name for the relation.
     *
     * @return string|array
     */
    public function getQualifiedParentKeyName()
    {
        if (is_array($this->parentKey)) {
            return array_map(fn ($k) => $this->parent->qualifyColumn($k), $this->parentKey);
        }

        return parent::getQualifiedParentKeyName();
    }

    /**
     * Get the fully-qualified related key name for the relation.
     *
     * @return string|array
     */
    public function getQualifiedRelatedKeyName()
    {
        if (is_array($this->relatedKey)) {
            return array_map(fn ($k) => $this->related->qualifyColumn($k), $this->relatedKey);
        }

        return parent::getQualifiedRelatedKeyName();
    }

    /**
     * Create a new pivot attachment record.
     *
     * @param int|array $id
     * @param bool      $timed
     *
     * @return array
     */
    protected function baseAttachRecord($id, $timed)
    {
        // Delegate to the stock implementation only when BOTH sides are scalar.
        // The previous "delegate when foreign is scalar" guard mishandled the
        // (scalar foreign, composite related) quadrant: parent::baseAttachRecord
        // would do `$record[$this->relatedPivotKey] = $id` with relatedPivotKey
        // as an array, throwing "Cannot access offset of type array on array".
        if (!$this->isComposite()) {
            return parent::baseAttachRecord($id, $timed);
        }

        $record = [];

        // Related side. Two shapes per side give four combos.
        if (is_array($this->relatedPivotKey)) {
            if (is_array($id)) {
                foreach ($this->relatedPivotKey as $index => $key) {
                    $record[$key] = $this->resolveBackedEnumValue($id[$index]);
                }
            } else {
                // Scalar id with composite related: assign to the first composite
                // column only. Remaining columns must be supplied via per-row or
                // bulk attributes that get merged on top of this record.
                $record[$this->relatedPivotKey[0]] = $this->resolveBackedEnumValue($id);
            }
        } else {
            $record[$this->relatedPivotKey] = $this->resolveBackedEnumValue($id);
        }

        // Foreign side. Pulled from the parent's parentKey value(s).
        if (is_array($this->foreignPivotKey)) {
            foreach ($this->foreignPivotKey as $index => $key) {
                $record[$key] = $this->resolveBackedEnumValue($this->parent->{$this->parentKey[$index]});
            }
        } else {
            $record[$this->foreignPivotKey] = $this->resolveBackedEnumValue(
                $this->parent->{$this->parentKey}
            );
        }

        if ($timed) {
            $record = $this->addTimestampsToAttachment($record);
        }

        foreach ($this->pivotValues as $value) {
            $record[$value['column']] = $value['value'];
        }

        return $record;
    }

    /**
     * Create an array of records to insert into the pivot table.
     *
     * Accepts the following input shapes for `$ids` on composite-key relations:
     *   - List of composite tuples: `[[1, 'FA'], [2, 'FA']]`
     *   - Map of `json_encode($tuple) => $perRowAttributes`: `['[1,"FA"]' => ['type' => 1]]`
     *   - Mixed within the same call.
     *
     * Per-row attributes override `$attributes` on key conflict.
     * Per-row attribute keys colliding with `foreignPivotKey` columns are dropped.
     *
     * @param array $ids
     * @param array $attributes
     *
     * @return array
     */
    protected function formatAttachRecords($ids, array $attributes)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::formatAttachRecords($ids, $attributes);
        }

        $records = [];

        $hasTimestamps = $this->hasPivotColumn($this->createdAt())
            || $this->hasPivotColumn($this->updatedAt());

        $castedBulkAttributes = $this->castAttributes($attributes);

        foreach ($ids as $key => $value) {
            [$tuple, $perRowAttributes] = $this->resolveCompositeAttachEntry($key, $value);

            $mergedAttributes = $perRowAttributes === []
                ? $castedBulkAttributes
                : array_merge($castedBulkAttributes, $this->castAttributes($perRowAttributes));

            $records[] = array_merge(
                $this->baseAttachRecord($tuple, $hasTimestamps),
                $mergedAttributes
            );
        }

        return $records;
    }

    /**
     * Resolve a single `$ids` entry into a [composite tuple or scalar id, per-row attributes] pair.
     *
     * Accepted shapes:
     *   - Int key + list-shaped value: `$value` is the full composite tuple.
     *   - Int key + associative or scalar value: `$key` is the scalar id (assigned
     *     to `relatedPivotKey[0]`), `$value` is the per-row attributes array.
     *   - String key that is `json_encode($tuple)` of matching arity: decoded tuple
     *     plus `$value` as per-row attributes.
     *   - String key that is a non-JSON scalar (e.g. UUID, slug): treated as the
     *     scalar id for `relatedPivotKey[0]`. Remaining composite columns must be
     *     supplied via per-row or bulk attributes.
     *   - String key that *attempts* JSON encoding (starts with `[` or `{`) but is
     *     malformed or has wrong arity: throws `InvalidUsageException`.
     *
     * @param int|string $key
     * @param mixed      $value
     *
     * @return array{0: array|int|string, 1: array}
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     */
    protected function resolveCompositeAttachEntry($key, $value): array
    {
        if (is_int($key)) {
            // Int key with list-shaped array value → value is the full composite tuple.
            if (is_array($value) && $this->isList($value)) {
                return [$value, []];
            }

            // Int key with associative array value → key is the scalar id,
            // value carries per-row pivot attributes (e.g. `[5 => ['attr' => 'val']]`).
            if (is_array($value)) {
                return [$key, $this->filterForeignPivotKeyAttributes($value)];
            }

            // Int key with scalar value → key is just an array index, value is the
            // scalar id. Covers `attach(['US', 'EU', 'AP'])` on asymmetric relations
            // where parseIds returns a flat list of scalar ids, one per row.
            return [$value, []];
        }

        // String key: try to decode as a JSON-encoded composite tuple first.
        $tuple = $this->decodeCompositeKey($key);

        if ($tuple !== null) {
            $perRowAttributes = is_array($value)
                ? $this->filterForeignPivotKeyAttributes($value)
                : [];

            return [$tuple, $perRowAttributes];
        }

        // String key that *attempts* JSON encoding (starts with `[` or `{`) but is
        // either malformed or has the wrong arity for `relatedPivotKey`.
        if (str_starts_with($key, '[') || str_starts_with($key, '{')) {
            throw new InvalidUsageException(sprintf(
                'Invalid composite-key array key %s passed to belongsToMany attach/sync. '.
                'Expected json_encode([...]) of arity %d (matching relatedPivotKey columns).',
                var_export($key, true),
                count($this->relatedPivotKey)
            ));
        }

        // Bare scalar string key (e.g. UUID, slug) → treat as the scalar id for
        // `relatedPivotKey[0]`. Remaining composite columns must be supplied via
        // per-row or bulk attributes (handled by the array_merge in
        // `formatAttachRecords` after `baseAttachRecord` runs).
        $perRowAttributes = is_array($value)
            ? $this->filterForeignPivotKeyAttributes($value)
            : [];

        return [$key, $perRowAttributes];
    }

    /**
     * Determine whether the given array is list-shaped (sequential integer keys
     * starting at 0). Internal helper for shape detection.
     */
    protected function isList(array $value): bool
    {
        return $value === [] || array_is_list($value);
    }

    /**
     * Decode a JSON-encoded composite-key string into its tuple form.
     *
     * Returns null when `$key` is not a string, is not valid JSON, does not
     * decode to an array, or has the wrong arity for `relatedPivotKey`.
     *
     * @param mixed $key
     *
     * @return array|null
     */
    protected function decodeCompositeKey($key): ?array
    {
        if (!is_string($key)) {
            return null;
        }

        $decoded = json_decode($key, true);

        if (!is_array($decoded) || count($decoded) !== count($this->relatedPivotKey)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Strip per-row attribute entries whose keys collide with `foreignPivotKey`
     * columns to prevent silent override of the parent-derived foreign keys.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function filterForeignPivotKeyAttributes(array $attributes): array
    {
        $foreignKeys = is_array($this->foreignPivotKey)
            ? $this->foreignPivotKey
            : [$this->foreignPivotKey];

        return array_diff_key($attributes, array_flip($foreignKeys));
    }

    /**
     * Get all of the IDs from the given mixed value.
     *
     * Each returned ID is a composite key tuple (array of values).
     *
     * @param mixed $value
     *
     * @return array
     */
    protected function parseIds($value)
    {
        if (!is_array($this->relatedKey)) {
            return parent::parseIds($value);
        }

        if ($value instanceof Model) {
            return [$this->extractCompositeKey($value)];
        }

        // Normalize any Support\Collection (or its Eloquent\Collection subclass) to a plain
        // array, then fall through to the array branch below. The array branch already
        // dispatches by first-element shape and handles the three input shapes uniformly:
        //   [Model, Model, ...]   → extractCompositeKey on each
        //   [tuple, tuple, ...]   → pass through
        //   [$id => $attrs, ...]  → preserve keys + values
        // The earlier eager extractCompositeKey mapping incorrectly assumed every item
        // was a Model and failed with TypeError on Collections of [id => attrs] maps.
        if ($value instanceof SupportCollection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            $first = reset($value);

            if ($first instanceof Model || is_array($first)) {
                return array_map(
                    fn ($item) => $item instanceof Model ? $this->extractCompositeKey($item) : $item,
                    $value
                );
            }

            // Flat list of scalars. Discriminate by foreignPivotKey shape:
            //  * Scalar foreignPivotKey (asymmetric mixed relation): each element
            //    is a separate scalar id, one row per id. Remaining composite
            //    columns on the related side come from $attributes via
            //    formatAttachRecords / baseAttachRecord's scalar-id branch.
            //  * Composite foreignPivotKey (both-composite relation): the flat
            //    list is the legacy "single composite tuple" shape.
            //    `attach(['EU', 2])` keeps meaning "attach one tuple".
            if (!is_array($this->foreignPivotKey)) {
                return $value;
            }

            return [$value];
        }

        return (array) $value;
    }

    /**
     * Get the ID from the given mixed value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function parseId($value)
    {
        if (!is_array($this->relatedKey)) {
            return parent::parseId($value);
        }

        return $value instanceof Model
            ? $this->extractCompositeKey($value)
            : $value;
    }

    /**
     * Create a new query builder for the pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function newPivotQuery()
    {
        if (!is_array($this->foreignPivotKey)) {
            return parent::newPivotQuery();
        }

        $query = $this->newPivotStatement();

        foreach ($this->pivotWheres as $arguments) {
            $query->where(...$arguments);
        }

        foreach ($this->pivotWhereIns as $arguments) {
            $query->whereIn(...$arguments);
        }

        foreach ($this->pivotWhereNulls as $arguments) {
            $query->whereNull(...$arguments);
        }

        foreach ($this->foreignPivotKey as $index => $key) {
            $query->where(
                $this->qualifyPivotColumn($key),
                $this->resolveBackedEnumValue($this->parent->{$this->parentKey[$index]})
            );
        }

        return $query;
    }

    /**
     * Get a new pivot statement for a given "other" ID.
     *
     * @param mixed $id
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function newPivotStatementForId($id)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::newPivotStatementForId($id);
        }

        return $this->newPivotQuery()->whereIn(
            $this->qualifyPivotColumn($this->relatedPivotKey),
            $this->parseIds($id)
        );
    }

    /**
     * Detach models from the relationship.
     *
     * @param mixed $ids
     * @param bool  $touch
     *
     * @return int
     */
    public function detach($ids = null, $touch = true)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::detach($ids, $touch);
        }

        if ($this->using) {
            $results = 0;
            $records = $this->getCurrentlyAttachedPivotsForIds($ids);

            foreach ($records as $record) {
                $results += $record->delete();
            }
        } else {
            $query = $this->newPivotQuery();

            if (!is_null($ids)) {
                $ids = $this->parseIds($ids);

                if (empty($ids)) {
                    return 0;
                }

                $query->whereIn(
                    $this->qualifyPivotColumn($this->relatedPivotKey),
                    $ids
                );
            }

            $results = $query->delete();
        }

        if ($touch) {
            $this->touchIfTouching();
        }

        return $results;
    }

    /**
     * Create a new pivot model instance.
     *
     * @param array $attributes
     * @param bool  $exists
     *
     * @return \Illuminate\Database\Eloquent\Relations\Pivot
     */
    public function newPivot(array $attributes = [], $exists = false)
    {
        if (!is_array($this->foreignPivotKey)) {
            return parent::newPivot($attributes, $exists);
        }

        $attributes = array_merge(array_column($this->pivotValues, 'value', 'column'), $attributes);

        $pivot = $this->using
            ? $this->using::fromRawAttributes($this->parent, $attributes, $this->table, $exists)
            : Pivot::fromAttributes($this->parent, $attributes, $this->table, $exists);

        return $pivot
            ->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey)
            ->setRelatedModel($this->related);
    }

    /**
     * Get the pivot models that are currently attached, filtered by related model keys.
     *
     * @param mixed $ids
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getCurrentlyAttachedPivotsForIds($ids = null)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::getCurrentlyAttachedPivotsForIds($ids);
        }

        return $this->newPivotQuery()
            ->when(!is_null($ids), function ($query) use ($ids) {
                return $query->whereIn(
                    $this->qualifyPivotColumn($this->relatedPivotKey),
                    $this->parseIds($ids)
                );
            })
            ->get()
            ->map(function ($record) {
                $class = $this->using ?: Pivot::class;

                $pivot = $class::fromRawAttributes($this->parent, (array) $record, $this->getTable(), true);

                return $pivot
                    ->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey)
                    ->setRelatedModel($this->related);
            });
    }

    /**
     * Format the sync / toggle record list so that it is keyed by ID.
     *
     * @param array $records
     *
     * @return array
     */
    protected function formatRecordsList(array $records)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::formatRecordsList($records);
        }

        $result = [];

        foreach ($records as $key => $value) {
            if (is_int($key) && is_array($value)) {
                $result[json_encode($value)] = [];
            } elseif (is_int($key)) {
                $result[$value] = [];
            } else {
                $result[$key] = is_array($value) ? $value : [];
            }
        }

        return $result;
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param mixed $ids
     * @param bool  $detaching
     *
     * @return array
     */
    public function sync($ids, $detaching = true)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::sync($ids, $detaching);
        }

        $changes = [
            'attached' => [], 'detached' => [], 'updated' => [],
        ];

        $records = $this->formatRecordsList($this->parseIds($ids));

        if (empty($records) && !$detaching) {
            return $changes;
        }

        $current = $this->getCurrentPivotKeys();

        if ($detaching) {
            $detach = array_diff($current, array_keys($records));

            if (count($detach) > 0) {
                $detachValues = array_values($detach);

                $this->detach($this->decodeJsonKeys($detachValues), false);

                $changes['detached'] = $this->decodeJsonKeys($detachValues);
            }
        }

        $changes = array_merge(
            $changes,
            $this->attachNew($records, $current, false)
        );

        if (count($changes['attached']) ||
            count($changes['updated']) ||
            count($changes['detached'])) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    /**
     * Toggles a model (or models) from the parent.
     *
     * @param mixed $ids
     * @param bool  $touch
     *
     * @return array
     */
    public function toggle($ids, $touch = true)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::toggle($ids, $touch);
        }

        $changes = [
            'attached' => [], 'detached' => [],
        ];

        $records = $this->formatRecordsList($this->parseIds($ids));
        $current = $this->getCurrentPivotKeys();

        $detach = array_values(array_intersect($current, array_keys($records)));

        if (count($detach) > 0) {
            $this->detach($this->decodeJsonKeys($detach), false);

            $changes['detached'] = $this->decodeJsonKeys($detach);
        }

        $attach = array_diff_key($records, array_flip($detach));

        if (count($attach) > 0) {
            foreach ($attach as $serializedId => $attributes) {
                // Wrap the decoded tuple in an outer array so parseIds lands in the
                // list-of-tuples branch. Without the wrapper, `attach(['EU', 2])` on a
                // (scalar foreign + composite related) relation would be re-interpreted
                // by parseIds as two separate scalar ids instead of one composite tuple.
                $this->attach([json_decode($serializedId, true)], $attributes, false);
            }

            $changes['attached'] = $this->decodeJsonKeys(array_keys($attach));
        }

        if ($touch && (count($changes['attached']) || count($changes['detached']))) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    /**
     * Attach all of the records that aren't in the given current records.
     *
     * @param array $records
     * @param array $current
     * @param bool  $touch
     *
     * @return array
     */
    protected function attachNew(array $records, array $current, $touch = true)
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::attachNew($records, $current, $touch);
        }

        $changes = ['attached' => [], 'updated' => []];

        foreach ($records as $id => $attributes) {
            if (!in_array($id, $current)) {
                // Wrap the decoded tuple in an outer array so parseIds lands in the
                // list-of-tuples branch. Without the wrapper, `attach(['EU', 2])` on a
                // (scalar foreign + composite related) relation would be re-interpreted
                // by parseIds as two separate scalar ids instead of one composite tuple.
                $this->attach([json_decode($id, true)], $attributes, $touch);

                $changes['attached'][] = json_decode($id, true);
            } elseif (count($attributes) > 0 &&
                $this->updateExistingPivot(json_decode($id, true), $attributes, $touch)) {
                $changes['updated'][] = json_decode($id, true);
            }
        }

        return $changes;
    }

    /**
     * Attach a model to the parent.
     *
     * @param mixed $ids
     * @param array $attributes
     * @param bool  $touch
     *
     * @return void
     */
    public function attach($ids, array $attributes = [], $touch = true)
    {
        if (!is_array($this->relatedPivotKey)) {
            parent::attach($ids, $attributes, $touch);

            return;
        }

        if ($this->using) {
            $records = $this->formatAttachRecords(
                $this->parseIds($ids),
                $attributes
            );

            foreach ($records as $record) {
                $this->newPivot($record, false)->save();
            }
        } else {
            $this->newPivotStatement()->insert($this->formatAttachRecords(
                $this->parseIds($ids),
                $attributes
            ));
        }

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    /**
     * Get the serialized keys of currently attached pivots.
     *
     * @return array
     */
    protected function getCurrentPivotKeys(): array
    {
        $pivots = $this->newPivotQuery()->get(
            $this->qualifyPivotColumn($this->relatedPivotKey)
        );

        return $pivots->map(fn ($record) => json_encode(
            array_map(fn ($k) => $record->{$k}, $this->relatedPivotKey)
        ))->all();
    }

    /**
     * Get all of the IDs for the related models.
     *
     * @return \Illuminate\Support\Collection
     */
    public function allRelatedIds()
    {
        if (!is_array($this->relatedPivotKey)) {
            return parent::allRelatedIds();
        }

        return $this->newPivotQuery()
            ->get($this->relatedPivotKey)
            ->map(fn ($record) => array_map(fn ($k) => $record->{$k}, $this->relatedPivotKey));
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Builder $parentQuery
     * @param array|mixed                           $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if (!is_array($this->parentKey)) {
            return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        }

        if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns);
        }

        $this->performJoin($query);

        return $query->select($columns)->whereColumn(
            $this->getQualifiedParentKeyName(),
            '=',
            $this->getQualifiedForeignPivotKeyName()
        );
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Builder $parentQuery
     * @param array|mixed                           $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQueryForSelfJoin(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if (!is_array($this->parentKey)) {
            return parent::getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns);
        }

        $query->select($columns);

        $hash = $this->getRelationCountHash();
        $query->from($this->related->getTable().' as '.$hash);
        $this->related->setTable($hash);

        $this->performJoin($query);

        return $query->whereColumn(
            $this->getQualifiedParentKeyName(),
            '=',
            $this->getQualifiedForeignPivotKeyName()
        );
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string|array
     */
    public function getExistenceCompareKey()
    {
        return $this->getQualifiedForeignPivotKeyName();
    }

    /**
     * Extract composite key values from a model using the related keys.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return array
     */
    protected function extractCompositeKey(Model $model): array
    {
        return array_map(fn ($k) => $model->{$k}, $this->relatedKey);
    }

    /**
     * Build a dictionary key by extracting and joining attribute values.
     *
     * @param object $source
     * @param array  $keys
     *
     * @return string
     */
    protected function buildDictionaryKey($source, array $keys): string
    {
        $values = array_map(fn ($k) => $this->resolveBackedEnumValue($source->{$k}), $keys);

        return implode('-', $values);
    }

    /**
     * Decode an array of JSON-encoded keys into their original array form.
     *
     * @param array $keys
     *
     * @return array
     */
    protected function decodeJsonKeys(array $keys): array
    {
        return array_map(fn ($key) => json_decode($key, true), $keys);
    }
}
