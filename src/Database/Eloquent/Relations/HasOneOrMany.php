<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Awobaz\Compoships\Concerns\BuildsCompositeEagerConstraints;
use Awobaz\Compoships\Concerns\ResolvesBackedEnumValues;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

trait HasOneOrMany
{
    use BuildsCompositeEagerConstraints;
    use ResolvesBackedEnumValues;

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

            $parentKeyValue = is_array($parentKeyValue)
                ? array_map(fn ($v) => $this->resolveBackedEnumValue($v), $parentKeyValue)
                : $parentKeyValue;

            //If the foreign key is an array (multi-column relationship), we adjust the query.
            if (is_array($this->foreignKey)) {
                $query = $this->getRelationQuery();
                $allParentKeyValuesAreNull = array_filter($parentKeyValue, fn ($value) => $value !== null) === [];

                foreach ($this->foreignKey as $index => $key) {
                    if (is_string($key)) {
                        $tmp = explode('.', $key);
                        $key = end($tmp);
                        $fullKey = $this->getRelated()
                                ->getTable().'.'.$key;
                    } else {
                        $fullKey = $key;
                    }
                    $query->where($fullKey, '=', $parentKeyValue[$index]);

                    if ($allParentKeyValuesAreNull) {
                        $query->whereNotNull($fullKey);
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
        if (is_array($this->localKey)) { //Check for multi-columns relationship
            $this->rejectExpressionForeignKeys('matched during eager loading');

            $added = $this->addCompositeKeyConstraints(
                $this->getRelationQuery(),
                $this->foreignKey,
                $this->getKeys($models, $this->localKey)
            );

            if (!$added) {
                $this->eagerKeysWereEmpty = true;
            }
        } else {
            parent::addEagerConstraints($models);
        }
    }

    /**
     * Get the fully qualified parent key name.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        if (is_array($this->localKey)) { //Check for multi-columns relationship
            return array_map(fn ($k) => $this->parent->getTable().'.'.$k, $this->localKey);
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
            $grammar = $this->getConnection()->getQueryGrammar();

            return array_map(fn ($k) => last(explode('.', $grammar->isExpression($k) ? $k->getValue($grammar) : $k)), $key);
        } else {
            return last(explode('.', $key));
        }
    }

    /**
     * Insert new records or update the existing ones.
     *
     * @param array        $values
     * @param array|string $uniqueBy
     * @param array|null   $update
     *
     * @return int
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (!is_array($this->foreignKey)) {
            return parent::upsert($values, $uniqueBy, $update);
        }

        $this->rejectExpressionForeignKeys('written');

        if (!empty($values) && !is_array($values[array_key_first($values)])) {
            $values = [$values];
        }

        $foreignKey = $this->getForeignKeyName();
        $parentKeyValue = $this->getParentKey();

        foreach ($values as $recordIndex => $value) {
            foreach ($foreignKey as $keyIndex => $key) {
                $values[$recordIndex][$key] = $parentKeyValue[$keyIndex];
            }
        }

        return $this->getQuery()->upsert($values, $uniqueBy, $update);
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Builder $parentQuery
     * @param array|mixed                           $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQueryForSelfRelation(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query->from($query->getModel()
                ->getTable().' as '.$hash = $this->getRelationCountHash());

        $query->getModel()
            ->setTable($hash);

        return $query->select($columns)
            ->whereColumn(
                $this->getQualifiedParentKeyName(),
                '=',
                is_array($this->getForeignKeyName()) //Check for multi-columns relationship
                    ? array_map(fn ($k) => $hash.'.'.$k, $this->getForeignKeyName())
                    : $hash.'.'.$this->getForeignKeyName()
            );
    }

    /**
     * Match the eagerly loaded results to their many parents.
     *
     * @param array                                    $models
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string                                   $relation
     * @param string                                   $type
     *
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
            //If the local key is an array, we know it's a multi-column relationship
            //and build a collision-free dictionary key from the values
            $dictKey = is_array($key)
                ? $this->compositeDictionaryKey($key)
                : ($key ?? '');

            if (isset($dictionary[$dictKey])) {
                $related = $this->getRelationValue($dictionary, $dictKey, $type);
                $model->setRelation($relation, $related);

                // Apply the inverse relation if we have one...
                if (method_exists($this, 'applyInverseRelationToModel')) {
                    if ($type === 'one') {
                        $this->applyInverseRelationToModel($related, $model);
                    } else {
                        $this->applyInverseRelationToCollection($related, $model);
                    }
                }
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param \Illuminate\Database\Eloquent\Collection $results
     *
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
            if (is_array($foreign)) {
                $dictionary[$this->compositeDictionaryKey(array_map(fn ($k) => $result->{$k}, $foreign))][] = $result;
            } else {
                $dictionary[$result->{$foreign} ?? ''][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * Create a new instance of the related model without mass-assignment protection.
     *
     * Laravel's implementation assigns the single foreign key directly instead
     * of going through setForeignAttributesForCreate(), so the composite case
     * needs its own override.
     *
     * @param array $attributes
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function forceCreate(array $attributes = [])
    {
        $foreignKey = $this->getForeignKeyName();

        if (!is_array($foreignKey)) {
            return parent::forceCreate($attributes);
        }

        $this->rejectExpressionForeignKeys('written');

        $parentKeyValue = $this->getParentKey();

        foreach ($foreignKey as $index => $key) {
            $attributes[$key] = $parentKeyValue[$index];
        }

        return $this->applyInverseRelationToModel($this->related->forceCreate($attributes));
    }

    /**
     * Set the foreign ID for creating a related model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return void
     */
    protected function setForeignAttributesForCreate(Model $model)
    {
        $foreignKey = $this->getForeignKeyName();

        if (!is_array($foreignKey)) {
            parent::setForeignAttributesForCreate($model);

            return;
        }

        $this->rejectExpressionForeignKeys('written');

        $parentKeyValue = $this->getParentKey();

        foreach ($foreignKey as $index => $key) {
            $model->setAttribute($key, $parentKeyValue[$index]);
        }

        foreach ($this->getQuery()->pendingAttributes as $key => $value) {
            $attributes ??= $model->getAttributes();

            if (!array_key_exists($key, $attributes)) {
                $model->setAttribute($key, $value);
            }
        }

        $this->applyInverseRelationToModel($model);
    }

    /**
     * Add join query constraints for one of many relationships.
     *
     * @param \Illuminate\Database\Eloquent\JoinClause $join
     *
     * @return void
     */
    public function addOneOfManyJoinSubQueryConstraints(JoinClause $join)
    {
        if (is_array($this->foreignKey)) {
            foreach ($this->foreignKey as $key) {
                $join->on($this->qualifySubSelectColumn($key), '=', $this->qualifyRelatedColumn($key));
            }
        } else {
            parent::addOneOfManyJoinSubQueryConstraints($join);
        }
    }

    /**
     * Expression foreign keys can constrain a query but name no attribute, so
     * they cannot be written to a model or used to match eager-loaded rows.
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     */
    protected function rejectExpressionForeignKeys(string $operation): void
    {
        $grammar = $this->getConnection()->getQueryGrammar();

        foreach ((array) $this->foreignKey as $key) {
            if ($grammar->isExpression($key)) {
                throw new InvalidUsageException(sprintf(
                    'A %s relation on %s has a foreign key containing a query expression, which cannot be %s. '.
                    'Expression keys support lazy loading and query constraints only.',
                    class_basename(static::class),
                    get_class($this->parent),
                    $operation
                ));
            }
        }
    }
}
