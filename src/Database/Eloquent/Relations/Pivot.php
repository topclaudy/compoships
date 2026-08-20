<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Relations\Pivot as BasePivot;

class Pivot extends BasePivot
{
    /**
     * Set the keys for a select query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSelectQuery($query)
    {
        if (isset($this->attributes[$this->getKeyName()])) {
            return parent::setKeysForSelectQuery($query);
        }

        $this->addCompositeKeyWhereClause($query, $this->foreignKey);
        $this->addCompositeKeyWhereClause($query, $this->relatedKey);

        return $query;
    }

    /**
     * Get the query builder for a delete operation on the pivot.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getDeleteQuery()
    {
        $query = $this->newQueryWithoutRelationships();

        $this->addCompositeKeyWhereClause($query, $this->foreignKey);
        $this->addCompositeKeyWhereClause($query, $this->relatedKey);

        return $query;
    }

    /**
     * Get the queueable identity for the entity.
     *
     * Pivots without a surrogate key are identified by every foreign and
     * related key column, JSON-encoded so values containing any separator
     * round-trip. The 3.1.x colon-separated format is still understood when
     * restoring (see legacyColumnValuesFromId()) and is removed in 4.0.
     *
     * @return mixed
     */
    public function getQueueableId()
    {
        if (isset($this->attributes[$this->getKeyName()])) {
            return $this->getKey();
        }

        $values = [];

        foreach ([...$this->getKeysAsArray($this->foreignKey), ...$this->getKeysAsArray($this->relatedKey)] as $key) {
            $values[$key] = $this->getAttribute($key);
        }

        return json_encode($values);
    }

    /**
     * Get a new query to restore one or more models by their queueable IDs.
     *
     * @param array|int|string $ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQueryForRestoration($ids)
    {
        if (is_array($ids)) {
            return $this->newQueryForCollectionRestoration($ids);
        }

        $columnValues = $this->columnValuesFromQueueableId($ids);

        if ($columnValues === null) {
            return parent::newQueryForRestoration($ids);
        }

        $query = $this->newQueryWithoutScopes();

        foreach ($columnValues as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    /**
     * @param array<int, mixed> $ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function newQueryForCollectionRestoration(array $ids)
    {
        $ids = array_values($ids);

        if ($ids === [] || $this->columnValuesFromQueueableId($ids[0]) === null) {
            return parent::newQueryForRestoration($ids);
        }

        $query = $this->newQueryWithoutScopes();

        foreach ($ids as $id) {
            $columnValues = $this->columnValuesFromQueueableId($id) ?? [];

            $query->orWhere(function ($query) use ($columnValues) {
                foreach ($columnValues as $column => $value) {
                    $query->where($column, $value);
                }
            });
        }

        return $query;
    }

    /**
     * Decode a composite queueable id into column => value pairs, accepting the
     * JSON form and the legacy colon form; null when the id is not composite.
     *
     * @param mixed $id
     *
     * @return array<string, mixed>|null
     */
    protected function columnValuesFromQueueableId($id): ?array
    {
        if (!is_string($id)) {
            return null;
        }

        if (str_starts_with($id, '{')) {
            $decoded = json_decode($id, true);

            return is_array($decoded) ? $decoded : null;
        }

        return $this->legacyColumnValuesFromId($id);
    }

    /**
     * Decode the 3.1.x `column:value:column:value` format. Kept for jobs queued
     * before the JSON format; remove in 4.0.
     *
     * @return array<string, mixed>|null
     */
    protected function legacyColumnValuesFromId(string $id): ?array
    {
        if (!str_contains($id, ':')) {
            return null;
        }

        $segments = explode(':', $id);

        if (count($segments) % 2 !== 0) {
            return null;
        }

        $columnValues = [];

        for ($i = 0; $i < count($segments); $i += 2) {
            $columnValues[$segments[$i]] = $segments[$i + 1];
        }

        return $columnValues;
    }

    /**
     * Add where clauses for a key that may be a string or an array.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array                          $key
     *
     * @return void
     */
    protected function addCompositeKeyWhereClause($query, $key): void
    {
        foreach ($this->getKeysAsArray($key) as $k) {
            $query->where($k, $this->getOriginal($k, $this->getAttribute($k)));
        }
    }

    /**
     * Normalize a key to always be an array.
     *
     * @param string|array $key
     *
     * @return array
     */
    protected function getKeysAsArray($key): array
    {
        return is_array($key) ? $key : [$key];
    }
}
