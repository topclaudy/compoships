<?php

namespace Awobaz\Compoships\Database\Query;

use Illuminate\Database\Query\Builder as BaseQueryBuilder;

class Builder extends BaseQueryBuilder
{
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        //Here we implement custom support for multi-column 'IN'
        //A multi-column 'IN' is a series of OR/AND clauses
        if(is_array($column)){
            $this->where(function($query) use ($column, $values){
                foreach($values as $value){
                    $query->orWhere(function($query) use ($column, $value){
                        foreach($column as $index => $aColumn){
                            $query->where($aColumn, $value[$index]);
                        }
                    });
                }
            });

            return $this;
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }
}
