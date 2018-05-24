<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany as BaseHasOneOrMany;

abstract class HasOneOrMany extends BaseHasOneOrMany
{
    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $foreignKey = $this->getForeignKeyName();
            $parentKeyValue = $this->getParentKey();

            //If the foreign key is an array (multi-column relationship), we adjust the query.
            if(is_array($this->foreignKey)) {
                foreach ($this->foreignKey as $index => $key){
                    list(, $key) = explode('.', $key);
                    $fullKey = $this->getRelated()->getTable() . '.' . $key;
                    $this->query->where($fullKey, '=', $parentKeyValue[$index]);
                    $this->query->whereNotNull($fullKey);
                }
            } else {
                $fullKey = $this->getRelated()->getTable() . '.' . $foreignKey;
                $this->query->where($fullKey, '=', $parentKeyValue);
                $this->query->whereNotNull($fullKey);
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
        $this->query->whereIn(
            $this->foreignKey, $this->getKeys($models, $this->localKey)
        );
    }


    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @param  string  $type
     * @return array
     */
    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            //If the foreign key is an array, we know it's a multi-column relationship
            //And we join the values to construct the dictionary key
            $dictKey = is_array($key) ? implode('-', $key) : $key;

            if (isset($dictionary[$dictKey])) {
                $model->setRelation(
                    $relation, $this->getRelationValue($dictionary, $dictKey, $type)
                );
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        $foreign = $this->getForeignKeyName();

        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly access all of the related
        // models without having to do nested looping which will be quite slow.
        foreach ($results as $result) {
            //If the foreign key is an array, we know it's a multi-column relationship...
            if(is_array($foreign)){
                $dictKeyValues = array_map(function($k) use ($result) {
                    return $result->{$k};
                }, $foreign);
                //... so we join the values to construct the dictionary key
                $dictionary[ implode('-', $dictKeyValues) ][] = $result;
            } else {
                $dictionary[ $result->{$foreign} ][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        if (is_array($this->localKey)) { //Check for multi-columns relationship
            return array_map(function ($k) {
                return $this->parent->getTable().'.'.$k;
            }, $this->localKey);
        } else {
            return $this->parent->getTable().'.'.$this->localKey;
        }
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        $key = $this->getQualifiedForeignKeyName();

        if (is_array($key)) { //Check for multi-columns relationship
            return array_map(function ($k) {
                $segments = explode('.', $k);

                return $segments[ count($segments) - 1 ];
            }, $key);
        } else {
            $segments = explode('.', $key);

            return $segments[ count($segments) - 1 ];
        }
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function save(Model $model)
    {
        $foreignKey = $this->getForeignKeyName();
        $parentKeyValue = $this->getParentKey();

        if(is_array($foreignKey)){ //Check for multi-columns relationship
            foreach ($foreignKey as $index => $key){
                $model->setAttribute($key, $parentKeyValue[$index]);
            }
        } else {
            $model->setAttribute($foreignKey, $parentKeyValue);
        }

        return $model->save() ? $model : false;
    }

    /**
     * Create a new instance of the related model.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $attributes = [])
    {
        return tap($this->related->newInstance($attributes), function ($instance) {
            $foreignKey = $this->getForeignKeyName();
            $parentKeyValue = $this->getParentKey();

            if(is_array($foreignKey)){ //Check for multi-columns relationship
                foreach ($foreignKey as $index => $key){
                    $instance->setAttribute($key, $parentKeyValue[$index]);
                }
            } else {
                $instance->setAttribute($foreignKey, $parentKeyValue);
            }

            $instance->save();
        });
    }
}