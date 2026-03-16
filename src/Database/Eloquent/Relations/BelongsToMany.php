<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as BaseBelongsToMany;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 * @template TPivotModel of \Illuminate\Database\Eloquent\Relations\Pivot
 *
 * @extends BaseBelongsToMany<TRelatedModel, TDeclaringModel, TPivotModel>
 */
class BelongsToMany extends BaseBelongsToMany
{
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

        $query = (method_exists($this, 'getRelationQuery') ? $this->getRelationQuery() : null) ?? $this->query;
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

        $where = collect($key)->filter(function ($key) use ($model) {
            return $model->getKeyName() === last(explode('.', $key))
                && in_array($model->getKeyType(), ['int', 'integer']);
        });

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
            $result[] = array_map(function ($k) use ($model) {
                return $this->resolveBackedEnumValue($model->{$k});
            }, $keys);
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
            return array_map(function ($col) {
                return parent::qualifyPivotColumn($col);
            }, $column);
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
            ->map(function ($column) {
                return $this->qualifyPivotColumn($column).' as pivot_'.$column;
            })
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
            return array_map(function ($k) {
                return $this->parent->qualifyColumn($k);
            }, $this->parentKey);
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
            return array_map(function ($k) {
                return $this->related->qualifyColumn($k);
            }, $this->relatedKey);
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
        if (!is_array($this->foreignPivotKey)) {
            return parent::baseAttachRecord($id, $timed);
        }

        $record = [];

        if (is_array($this->relatedPivotKey)) {
            foreach ($this->relatedPivotKey as $index => $key) {
                $value = is_array($id) ? $id[$index] : $id;
                $record[$key] = $this->resolveBackedEnumValue($value);
            }
        } else {
            $record[$this->relatedPivotKey] = $id;
        }

        foreach ($this->foreignPivotKey as $index => $key) {
            $record[$key] = $this->resolveBackedEnumValue($this->parent->{$this->parentKey[$index]});
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

        foreach ($ids as $value) {
            $records[] = array_merge(
                $this->baseAttachRecord($value, $hasTimestamps),
                $this->castAttributes($attributes)
            );
        }

        return $records;
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

        if ($value instanceof Collection) {
            return $value->map(function ($model) {
                return $this->extractCompositeKey($model);
            })->all();
        }

        if (is_array($value)) {
            $first = reset($value);

            if ($first instanceof Model || is_array($first)) {
                return array_map(function ($item) {
                    return $item instanceof Model
                        ? $this->extractCompositeKey($item)
                        : $item;
                }, $value);
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
            $results = $this->detachUsingCustomClass($ids);
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
                $this->attach(json_decode($serializedId, true), $attributes, false);
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
                $this->attach(json_decode($id, true), $attributes, $touch);

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
            $this->attachUsingCustomClass($ids, $attributes);
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

        return $pivots->map(function ($record) {
            $values = array_map(function ($k) use ($record) {
                return $record->{$k};
            }, $this->relatedPivotKey);

            return json_encode($values);
        })->all();
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
            ->map(function ($record) {
                return array_map(function ($k) use ($record) {
                    return $record->{$k};
                }, $this->relatedPivotKey);
            });
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
        return array_map(function ($k) use ($model) {
            return $model->{$k};
        }, $this->relatedKey);
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
        $values = array_map(function ($k) use ($source) {
            return $this->resolveBackedEnumValue($source->{$k});
        }, $keys);

        return implode('-', $values);
    }

    /**
     * Resolve a BackedEnum to its scalar value, or return the value as-is.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function resolveBackedEnumValue($value)
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
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
        return array_map(function ($key) {
            return json_decode($key, true);
        }, $keys);
    }
}
