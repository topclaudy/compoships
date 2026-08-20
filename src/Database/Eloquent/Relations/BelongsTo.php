<?php

namespace Awobaz\Compoships\Database\Eloquent\Relations;

use Awobaz\Compoships\Concerns\BuildsCompositeEagerConstraints;
use Awobaz\Compoships\Concerns\ResolvesBackedEnumValues;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BaseBelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends BaseBelongsTo<TRelatedModel,TDeclaringModel, TRelatedModel>
 */
class BelongsTo extends BaseBelongsTo
{
    use BuildsCompositeEagerConstraints;
    use ResolvesBackedEnumValues;

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        if (!is_array($this->foreignKey)) {
            return parent::getResults();
        }

        if ($this->childForeignKeyValuesAreUnusable()) {
            return $this->getDefaultFor($this->parent);
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

        if ($model === null) {
            return $this->dissociate();
        }

        $ownerKey = $model instanceof Model ? $model->getAttribute($this->ownerKey) : $model;

        if (!is_array($ownerKey) || count($ownerKey) !== count($this->foreignKey)) {
            throw new InvalidUsageException(sprintf(
                'associate() on the composite relation %s::%s() expects a model or an array of %d key values, got %s.',
                get_class($this->child),
                $this->relationName,
                count($this->foreignKey),
                get_debug_type($ownerKey)
            ));
        }

        foreach ($this->foreignKey as $index => $foreignKey) {
            $this->child->setAttribute($foreignKey, $ownerKey[$index]);
        }

        if ($model instanceof Model) {
            $this->child->setRelation($this->relationName, $model);
        } elseif ($this->child->isDirty($this->foreignKey)) {
            // proper unset // https://github.com/illuminate/database/commit/44411c7288fc7b7d4e5680cfcdaa46d348b5c981
            $this->child->unsetRelation($this->relationName);
        }

        return $this->child;
    }

    /**
     * Dissociate previously associated model from the given parent.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function dissociate()
    {
        if (!is_array($this->foreignKey)) {
            return parent::dissociate();
        }

        foreach ($this->foreignKey as $foreignKey) {
            $this->child->setAttribute($foreignKey, null);
        }

        return $this->child->setRelation($this->relationName, null);
    }

    /**
     * Get the value of the model's foreign key (every column for composite keys).
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return mixed
     */
    protected function getForeignKeyFrom(Model $model)
    {
        if (!is_array($this->foreignKey)) {
            return parent::getForeignKeyFrom($model);
        }

        return array_map(fn ($key) => $this->resolveBackedEnumValue($model->getAttribute($key)), $this->foreignKey);
    }

    /**
     * Get the value of the related model's owner key (every column for composite keys).
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return mixed
     */
    protected function getRelatedKeyFrom(Model $model)
    {
        if (!is_array($this->ownerKey)) {
            return parent::getRelatedKeyFrom($model);
        }

        return array_map(fn ($key) => $this->resolveBackedEnumValue($model->getAttribute($key)), $this->ownerKey);
    }

    /**
     * Compare the parent key with the related key, element-wise for composite
     * keys. Components compare by value (1 equals '1'); null matches only null;
     * all-null keys never match, mirroring the scalar "empty key" rule.
     *
     * @param mixed $parentKey
     * @param mixed $relatedKey
     *
     * @return bool
     */
    protected function compareKeys($parentKey, $relatedKey)
    {
        if (!is_array($parentKey) || !is_array($relatedKey)) {
            return parent::compareKeys($parentKey, $relatedKey);
        }

        if (count($parentKey) !== count($relatedKey)) {
            return false;
        }

        if (array_filter($parentKey, fn ($value) => $value !== null) === []) {
            return false;
        }

        return $this->compositeDictionaryKey($parentKey) === $this->compositeDictionaryKey($relatedKey);
    }

    /**
     * Touch all of the related models for the relationship.
     *
     * @return void
     */
    public function touch()
    {
        if (!is_array($this->foreignKey)) {
            parent::touch();

            return;
        }

        if ($this->childForeignKeyValuesAreUnusable()) {
            return;
        }

        Relation::touch();
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
                $foreignKeyValues = $this->childForeignKeyValues();
                $allForeignKeyValuesAreNull = array_filter($foreignKeyValues, fn ($value) => $value !== null) === [];

                foreach ($this->ownerKey as $index => $key) {
                    $fullKey = $table.'.'.$key;

                    $this->query->where($fullKey, '=', $foreignKeyValues[$index]);

                    if ($allForeignKeyValuesAreNull) {
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

            $added = $this->addCompositeKeyConstraints(
                $this->getRelationQuery(),
                $keys,
                $this->getEagerModelKeysForArray($models)
            );

            if (!$added) {
                $this->eagerKeysWereEmpty = true;
            }
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
            $keys[] = array_map(fn ($k) => $model->{$k}, $this->foreignKey);
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
            return array_map(fn ($k) => $this->child->getTable().'.'.$k, $this->foreignKey);
        } else {
            return $this->child->getTable().'.'.$this->foreignKey;
        }
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
        if (!is_array($this->ownerKey)) {
            return parent::getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        $query->select($columns)->from(
            $query->getModel()->getTable().' as '.$hash = $this->getRelationCountHash()
        );

        $query->getModel()->setTable($hash);

        return $query->whereColumn(
            array_map(fn ($k) => $hash.'.'.$k, $this->ownerKey),
            '=',
            $this->getQualifiedForeignKeyName()
        );
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
                is_array($this->ownerKey) //Check for multi-columns relationship
                    ? array_map(fn ($k) => $modelTable.'.'.$k, $this->ownerKey)
                    : $modelTable.'.'.$this->ownerKey
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
                $dictionary[$this->compositeDictionaryKey(array_map(fn ($k) => $result->{$k}, $owner))] = $result;
            } else {
                $dictionary[$result->getAttribute($owner) ?? ''] = $result;
            }
        }

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            if (is_array($foreign)) { //Check for multi-columns relationship
                $key = $this->compositeDictionaryKey(array_map(fn ($k) => $model->{$k}, $foreign));
            } else {
                $key = $model->{$foreign} ?? '';
            }

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * The child's foreign key values, enum-resolved, in foreign key order.
     *
     * @return array<int, mixed>
     */
    protected function childForeignKeyValues(): array
    {
        return array_map(
            fn ($key) => $this->resolveBackedEnumValue($this->child->getAttribute($key)),
            $this->foreignKey
        );
    }

    /**
     * Whether the child cannot identify a parent: a foreign key attribute is
     * absent (for example a partial select) or every value is null.
     */
    protected function childForeignKeyValuesAreUnusable(): bool
    {
        $attributes = $this->child->getAttributes();

        foreach ($this->foreignKey as $key) {
            if (!array_key_exists($key, $attributes)) {
                return true;
            }
        }

        return array_filter($this->childForeignKeyValues(), fn ($value) => $value !== null) === [];
    }
}
