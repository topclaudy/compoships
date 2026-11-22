<?php

namespace Awobaz\Compoships\Concerns;

use Illuminate\Contracts\Database\Query\Expression;

trait AddsMissingKeyColumnsToEagerSelect
{
    /**
     * Add the relationship keys that are missing from an explicit eager load select clause.
     *
     * Laravel applies the "select only these columns" constraint of `with('relation:column,...')`
     * after the eager constraints have been added to the relationship. On composite key
     * relationships that drops the key columns which are required to match the related models
     * back to their parents, so the relationship resolves to an empty result. We restore them
     * right before the eager query is executed.
     *
     * @param array $keys
     *
     * @return void
     */
    protected function addMissingKeyColumnsToEagerSelect(array $keys)
    {
        $queries = [$this->query];

        //One of many relationships keep their constraints on a dedicated sub query.
        $queries[] = $this->getRelationQuery();

        $patched = [];

        foreach ($queries as $query) {
            if (is_null($query) || isset($patched[spl_object_id($query)])) {
                continue;
            }

            $patched[spl_object_id($query)] = true;

            $this->addMissingColumnsToSelect($query->getQuery(), $keys);
        }
    }

    /**
     * Append the given columns to a query select clause when they are not selected yet.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array                              $columns
     *
     * @return void
     */
    protected function addMissingColumnsToSelect($query, array $columns)
    {
        if (empty($query->columns)) {
            return; //The query selects everything, there is nothing to restore.
        }

        $selected = [];

        foreach ($query->columns as $column) {
            $selected[] = $this->selectedColumnName($column);
        }

        if (in_array('*', $selected, true)) {
            return; //A wildcard already selects the key columns.
        }

        foreach ($columns as $column) {
            $name = $this->selectedColumnName($column);

            if (is_null($name) || in_array($name, $selected, true)) {
                continue;
            }

            $query->addSelect($column);
            $selected[] = $name;
        }
    }

    /**
     * Get the comparable name of a column of a select clause.
     *
     * @param string|\Illuminate\Contracts\Database\Query\Expression $column
     *
     * @return string|null
     */
    protected function selectedColumnName($column)
    {
        if ($column instanceof Expression) {
            return null;
        }

        $name = preg_replace('/\s+as\s+.*$/i', '', trim((string) $column));

        return strtolower(last(explode('.', trim($name))));
    }
}
