<?php

namespace Awobaz\Compoships\Database\Query;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Arr;

class Builder extends BaseQueryBuilder
{
    /**
     * Add a "where in" clause to the query.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string|string[] $column
     * @param mixed                                                           $values
     * @param string                                                          $boolean
     * @param bool                                                            $not
     *
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        // Here we implement custom support for multi-column 'IN'
        if (is_array($column)) {
            $connection = $this->getConnection();
            // Check if we can use optimized row value expressions
            if (
                ($connection instanceof MySqlConnection ||
                    $connection instanceof PostgresConnection ||
                    $connection instanceof SQLiteConnection) &&
                Arr::every($values, fn ($value) => !in_array(null, $value, true))
            ) {
                $inOperator = $not ? 'NOT IN' : 'IN';
                $prefix = $connection->getTablePrefix();

                foreach ($column as &$value) {
                    $value = $prefix.$value;
                }

                $columns = implode(',', $column);
                $tuplePlaceholders = '('.implode(',', array_fill(0, count($column), '?')).')';
                $placeholderList = implode(',', array_fill(0, count($values), $tuplePlaceholders));
                $this->whereRaw("({$columns}) {$inOperator} (VALUES {$placeholderList})", Arr::flatten($values), $boolean);

                return $this;
            }

            // Otherwise use a series of OR/AND clauses
            $this->where(function ($query) use ($column, $values) {
                foreach ($values as $value) {
                    $query->orWhere(function ($query) use ($column, $value) {
                        foreach ($column as $index => $aColumn) {
                            $query->where($aColumn, $value[$index]);
                        }
                    });
                }
            });

            return $this;
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and')
    {
        // If the column and values are arrays, we will assume it is a multi-columns relationship
        // and we adjust the 'where' clauses accordingly
        if (is_array($first) && is_array($second)) {
            $type = 'Column';

            foreach ($first as $index => $f) {
                $this->wheres[] = [
                    'type'     => $type,
                    'first'    => $f,
                    'operator' => $operator,
                    'second'   => $second[$index],
                    'boolean'  => $boolean,

                ];
            }

            return $this;
        }

        return parent::whereColumn($first, $operator, $second, $boolean);
    }
}
