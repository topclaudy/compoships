<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne as BaseHasOne;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends BaseHasOne<TRelatedModel, TDeclaringModel>
 */
class HasOne extends BaseHasOne
{
    use HasOneOrMany;

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        if (!is_array($this->getParentKey())) {
            if (is_null($this->getParentKey())) {
                return $this->getDefaultFor($this->parent);
            }
        }

        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array  $models
     * @param string $relation
     *
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
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Make a new related instance for the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model $parent
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function newRelatedInstanceFor(Model $parent)
    {
        if (!is_array($this->localKey)) {
            return parent::newRelatedInstanceFor($parent);
        }

        $newInstance = $this->related->newInstance();
        $foreignKey = $this->getForeignKeyName();

        foreach ($this->localKey as $index => $key) {
            $newInstance->setAttribute($foreignKey[$index], $parent->getAttribute($key));
        }

        return $newInstance;
    }
}
