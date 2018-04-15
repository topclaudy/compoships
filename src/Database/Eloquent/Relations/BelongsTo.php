<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BaseBelongsTo;

class BelongsTo extends BaseBelongsTo
{
    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $table = $this->related->getTable();

            if(is_array($this->ownerKey)){ //Check for multi-columns relationship
                foreach($this->ownerKey as $index => $key){
                    if($this->child->{$this->foreignKey[$index]}) {
                        $this->query->where($table . '.' . $key, '=', $this->child->{$this->foreignKey[ $index ]});
                    }
                }
            } else {
                $this->query->where($table.'.'.$this->ownerKey, '=', $this->child->{$this->foreignKey});
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        if(is_array($this->ownerKey)){ //Check for multi-columns relationship
            $keys = [];

            foreach ($this->ownerKey as $key){
                $keys[] = $this->related->getTable().'.'.$key;
            }

            $this->query->whereIn($keys, $this->getEagerModelKeys($models));
        } else {
            // We'll grab the primary key name of the related models since it could be set to
            // a non-standard name and not "id". We will then construct the constraint for
            // our eagerly loading query so it returns the proper models from execution.
            $key = $this->related->getTable().'.'.$this->ownerKey;
            $this->query->whereIn($key, $this->getEagerModelKeys($models));
        }
    }

    /**
     * Gather the keys from an array of related models.
     *
     * @param  array  $models
     * @return array
     */
    protected function getEagerModelKeys(array $models)
    {
        $keys = [];


        // First we need to gather all of the keys from the parent models so we know what
        // to query for via the eager loading query. We will add them to an array then
        // execute a "where in" statement to gather up all of those related records.
        foreach ($models as $model) {
            if(is_array($this->foreignKey)){ //Check for multi-columns relationship
                $keys[] = array_map(function($k) use ($model) {
                    return $model->{$k};
                }, $this->foreignKey);
            } else {
                if (!is_null($value = $model->{$this->foreignKey})) {
                    $keys[] = $value;
                }
            }
        }

        // If there are no keys that were not null we will just return an array with either
        // null or 0 in (depending on if incrementing keys are in use) so the query wont
        // fail plus returns zero results, which should be what the developer expects.
        if (count($keys) === 0) {
            return [$this->relationHasIncrementingId() ? 0 : null];
        }

        sort($keys);

        return array_values(array_unique($keys, SORT_REGULAR));
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        $foreign = $this->foreignKey;

        $owner = $this->ownerKey;

        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = [];

        foreach ($results as $result) {
            if(is_array($owner)){ //Check for multi-columns relationship
                $dictKeyValues = array_map(function($k) use ($result) {
                    return $result->{$k};
                }, $owner);

                $dictionary[ implode('-', $dictKeyValues) ] = $result;
            } else {
                $dictionary[$result->getAttribute($owner)] = $result;
            }
        }

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            if(is_array($foreign)){ //Check for multi-columns relationship
                $dictKeyValues = array_map(function($k) use ($model) {
                    return $model->{$k};
                }, $foreign);

                $key = implode('-', $dictKeyValues);
            } else {
                $key = $model->{$foreign};
            }

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }
}
