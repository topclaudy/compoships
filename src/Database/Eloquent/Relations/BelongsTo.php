<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BaseBelongsTo;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TChildModel of \Illuminate\Database\Eloquent\Model
 * @extends BaseBelongsTo<TRelatedModel,TChildModel>
 */
class BelongsTo extends BaseBelongsTo
{
    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        if (!is_array($this->foreignKey)) {
            if (is_null($this->child->{$this->foreignKey})) {
                return $this->getDefaultFor($this->parent);
            }
        }

        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Associate the model instance to the given parent.
     *
     * @param \Illuminate\Database\Eloquent\Model|int|string|null $model
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function associate($model)
    {
        if (!is_array($this->ownerKey)) {
            return parent::associate($model);
        }

        $ownerKey = $model instanceof Model ? $model->getAttribute($this->ownerKey) : $model;
        for ($i = 0; $i < count($this->foreignKey); $i++) {
            $foreignKey = $this->foreignKey[$i];
            $value = $ownerKey[$i];
            $this->child->setAttribute($foreignKey, $value);
        }
        // BC break in 5.8 : https://github.com/illuminate/database/commit/87b9833019f48b88d98a6afc46f38ce37f08237d
        $relationName = property_exists($this, 'relationName') ? $this->relationName : $this->relation;
        if ($model instanceof Model) {
            $this->child->setRelation($relationName, $model);
        // proper unset // https://github.com/illuminate/database/commit/44411c7288fc7b7d4e5680cfcdaa46d348b5c981
        } elseif ($this->child->isDirty($this->foreignKey)) {
            $this->child->unsetRelation($relationName);
        }

        return $this->child;
    }

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

            if (is_array($this->ownerKey)) { //Check for multi-columns relationship
                $childAttributes = $this->child->getAttributes();

                $allOwnerKeyValuesAreNull = array_unique(array_values(
                    array_intersect_key($childAttributes, array_flip($this->ownerKey))
                )) === [null];

                foreach ($this->ownerKey as $index => $key) {
                    $fullKey = $table.'.'.$key;

                    if (array_key_exists($this->foreignKey[$index], $childAttributes)) {
                        $this->query->where($fullKey, '=', $this->child->{$this->foreignKey[$index]});
                    }

                    if ($allOwnerKeyValuesAreNull) {
                        $this->query->whereNotNull($fullKey);
                    }
                }
            } else {
                parent::addConstraints();
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        if (is_array($this->ownerKey)) { //Check for multi-columns relationship
            $keys = [];

            foreach ($this->ownerKey as $key) {
                $keys[] = $this->related->getTable().'.'.$key;
            }

            // method \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany::whereInMethod
            // 5.6 - does not exist
            // 5.7 - added in 5.7.17 / https://github.com/illuminate/database/commit/9af300d1c50c9ec526823c1e6548daa3949bf9a9
            $this->query->whereIn($keys, $this->getEagerModelKeys($models));
        } else {
            parent::addEagerConstraints($models);
        }
    }

    /**
     * Gather the keys from an array of related models.
     *
     * @param array $models
     *
     * @return array
     */
    protected function getEagerModelKeys(array $models)
    {
        if (is_array($this->foreignKey)) {
            return $this->getEagerModelKeysForArray($models);
        }

        return parent::getEagerModelKeys($models);
    }

    /**
     * Gather the keys from an array of related models that
     * are using a composite related key.
     *
     * @param array $models
     *
     * @return array
     */
    protected function getEagerModelKeysForArray(array $models)
    {
        $keys = [];

        // First we need to gather all of the keys from the parent models so we know what
        // to query for via the eager loading query. We will add them to an array then
        // execute a "where in" statement to gather up all of those related records.
        foreach ($models as $model) {
            $keys[] = array_map(function ($k) use ($model) {
                return $model->{$k};
            }, $this->foreignKey);
        }

        sort($keys);

        return array_map('unserialize', array_unique(array_map('serialize', $keys)));
    }

    /**
     * Get the fully qualified foreign key of the relationship.
     *
     * @return string
     */
    public function getQualifiedForeignKey()
    {
        if (is_array($this->foreignKey)) { //Check for multi-columns relationship
            return array_map(function ($k) {
                return $this->child->getTable().'.'.$k;
            }, $this->foreignKey);
        } else {
            return $this->child->getTable().'.'.$this->foreignKey;
        }
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Builder $parentQuery
     * @param array|mixed                           $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        $modelTable = $query->getModel()
            ->getTable();

        return $query->select($columns)
            ->whereColumn(
                $this->getQualifiedForeignKey(),
                '=',
                is_array($this->ownerKey) ? //Check for multi-columns relationship
                    array_map(function ($k) use ($modelTable) {
                        return $modelTable.'.'.$k;
                    }, $this->ownerKey) : $modelTable.'.'.$this->ownerKey
            );
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array                                    $models
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string                                   $relation
     *
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
            if (is_array($owner)) { //Check for multi-columns relationship
                $dictKeyValues = array_map(function ($k) use ($result) {
                    return $result->{$k};
                }, $owner);

                $dictionary[implode('-', $dictKeyValues)] = $result;
            } else {
                $dictionary[$result->getAttribute($owner)] = $result;
            }
        }

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            if (is_array($foreign)) { //Check for multi-columns relationship
                $dictKeyValues = array_map(function ($k) use ($model) {
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
