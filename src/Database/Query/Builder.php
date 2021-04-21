<?php

namespace Awobaz\Compoships\Database\Query;

use Illuminate\Database\Query\Builder as BaseQueryBuilder;

class Builder extends BaseQueryBuilder
{
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
