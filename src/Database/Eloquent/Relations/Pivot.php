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
     * @return mixed
     */
    public function getQueueableId()
    {
        if (isset($this->attributes[$this->getKeyName()])) {
            return $this->getKey();
        }

        $parts = [];

        foreach ($this->getKeysAsArray($this->foreignKey) as $key) {
            $parts[] = $key;
            $parts[] = $this->getAttribute($key);
        }

        foreach ($this->getKeysAsArray($this->relatedKey) as $key) {
            $parts[] = $key;
            $parts[] = $this->getAttribute($key);
        }

        return implode(':', $parts);
    }

    /**
     * Get a new query to restore one or more models by their queueable IDs.
     *
     * @param int[]|string[]|string $ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQueryForRestoration($ids)
    {
        if (is_array($ids)) {
            return $this->newQueryForCollectionRestoration($ids);
        }

        if (strpos($ids, ':') === false) {
            return parent::newQueryForRestoration($ids);
        }

        $segments = explode(':', $ids);
        $query = $this->newQueryWithoutScopes();

        for ($i = 0; $i < count($segments); $i += 2) {
            $query->where($segments[$i], $segments[$i + 1]);
        }

        return $query;
    }

    /**
     * Get a new query to restore multiple models by their queueable IDs.
     *
     * @param int[]|string[] $ids
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function newQueryForCollectionRestoration(array $ids)
    {
        $ids = array_values($ids);

        if (strpos($ids[0], ':') === false) {
            return parent::newQueryForRestoration($ids);
        }

        $query = $this->newQueryWithoutScopes();

        foreach ($ids as $id) {
            $segments = explode(':', $id);

            $query->orWhere(function ($query) use ($segments) {
                for ($i = 0; $i < count($segments); $i += 2) {
                    $query->where($segments[$i], $segments[$i + 1]);
                }
            });
        }

        return $query;
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
