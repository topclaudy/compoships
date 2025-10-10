<?php

namespace Awobaz\Compoships\Database\Query;

use Illuminate\Database\Query\Builder as BaseQueryBuilder;
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
            $inOperator = $not ? 'NOT IN' : 'IN';
            $prefix = $this->getConnection()->getTablePrefix();
            
            foreach ($column as &$value) {               
                $value = $prefix.$value;
            }

            $columns = implode(',', $column);
            $tuplePlaceholders = '('.implode(', ', array_fill(0, count($column), '?')).')';
            $placeholderList = implode(',', array_fill(0, count($values), $tuplePlaceholders));
            $this->whereRaw("({$columns}) {$inOperator} ({$placeholderList})", Arr::flatten($values), $boolean);

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
