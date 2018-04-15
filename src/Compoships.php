<?php

namespace Awobaz\Compoships;

use Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships;
use Awobaz\Compoships\Database\Eloquent\Concerns\QueriesRelationships;
use Awobaz\Compoships\Database\Eloquent\Builder as EloquentBuilder;
use Awobaz\Compoships\Database\Query\Builder as QueryBuilder;

trait Compoships
{
    use HasRelationships, QueriesRelationships;

    public function getAttribute($key)
    {
        if(is_array($key)){ //Check for multi-columns relationship
            return array_map(function($k){
                return parent::getAttribute($k);
            }, $key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Configure Eloquent to use Compoships Query Builder
     *
     * @return \Awobaz\Compoships\Database\Query\Builder|static
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder(
            $connection, $connection->getQueryGrammar(), $connection->getPostProcessor()
        );
    }

    /**
     * Configure Eloquent to use Compoships Eloquent Builder
     *
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Awobaz\Compoships\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new EloquentBuilder($query);
    }
}
