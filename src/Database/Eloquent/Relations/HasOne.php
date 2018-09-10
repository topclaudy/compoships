<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;
use Illuminate\Database\Eloquent\Relations\HasMany as BaseHasMany;

class HasOne extends BaseHasMany
{
    use SupportsDefaultModels;

    /**
     * Indicates if a default model instance should be used.
     *
     * Alternatively, may be a Closure or array.
     *
     * @var \Closure|array|bool
     */
    protected $withDefault;

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Get the default value for this relation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getDefaultFor(Model $model)
    {
        if (! $this->withDefault) {
            return;
        }

        $instance = $this->related->newInstance();

        $foreignKey = $this->getForeignKeyName();

        if(is_array($foreignKey)){ //Check for multi-columns relationship
            foreach ($foreignKey as $index => $key){
                $instance->setAttribute($key, $model->getAttribute($this->localKey[$index]));
            }
        } else {
            $instance->setAttribute($foreignKey, $model->getAttribute($this->localKey));
        }

        if (is_callable($this->withDefault)) {
            return call_user_func($this->withDefault, $instance) ?: $instance;
        }

        if (is_array($this->withDefault)) {
            $instance->forceFill($this->withDefault);
        }

        return $instance;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array  $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Make a new related instance for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function newRelatedInstanceFor(Model $parent)
    {
        $newInstance = $this->related->newInstance();

        if(is_array($this->localKey)){ //Check for multi-columns relationship
            $foreignKey = $this->getForeignKeyName();

            foreach ($this->localKey as $index => $key){
                $newInstance->setAttribute($foreignKey[$index], $parent->{$key});
            }
        } else {
            return $newInstance->setAttribute(
                $this->getForeignKeyName(), $parent->{$this->localKey}
            );
        }
    }
}
