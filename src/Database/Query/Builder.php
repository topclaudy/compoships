<?php

namespace Awobaz\Compoships\Database\Query;

use Illuminate\Database\Query\Builder as BaseQueryBuilder;

class Builder extends BaseQueryBuilder
{
    /**
     * FROM: laravel-oci8
     * Add a "where in" clause to the query.
     * Split one WHERE IN clause into multiple clauses each
     * with up to 1000 expressions to avoid ORA-01795.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return \Illuminate\Database\Query\Builder
     */
    public function whereInOracle($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        if (is_array($values) && count($values) > 1000) {
            $chunks = array_chunk($values, 1000);

            return $this->where(function ($query) use ($column, $chunks, $type, $not) {
                foreach ($chunks as $ch) {
                    $sqlClause = $not ? 'where'.$type : 'orWhere'.$type;
                    $query->{$sqlClause}($column, $ch);
                }
            }, null, null, $boolean);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }


    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        //Here we implement custom support for multi-column 'IN'
        //A multi-column 'IN' is a series of OR/AND clauses
        //TODO: Optimization
        if (is_array($column)) {
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

        return $this->whereInOracle($column, $values, $boolean, $not);
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
