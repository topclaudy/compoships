<?php

namespace Awobaz\Compoships\Database\Eloquent\Concerns;

use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Database\Eloquent\Relations\HasMany;
use Awobaz\Compoships\Database\Eloquent\Relations\HasOne;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasRelationships
{
    /**
     * Get the table qualified key name.
     *
     * @return mixed
     */
    public function getQualifiedKeyName()
    {
        $keyName = $this->getKeyName();

        if (is_array($keyName)) { //Check for multi-columns relationship
            $keys = [];

            foreach ($keyName as $key) {
                $keys[] = $this->getTable().$key;
            }

            return $keys;
        }

        return $this->getTable().'.'.$keyName;
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param string            $related
     * @param string|array|null $foreignKey
     * @param string|array|null $localKey
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        if (is_array($foreignKey)) { //Check for multi-columns relationship
            $this->validateRelatedModel($related);
        }

        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $foreignKeys = null;

        if (is_array($foreignKey)) { //Check for multi-columns relationship
            foreach ($foreignKey as $key) {
                $foreignKeys[] = $this->sanitizeKey($instance, $key);
            }
        } else {
            $foreignKey = $this->sanitizeKey($instance, $foreignKey);
        }

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasOne($instance->newQuery(), $this, $foreignKeys ?: $foreignKey, $localKey);
    }

    /**
     * Instantiate a new HasOne relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model   $parent
     * @param string|array                          $foreignKey
     * @param string|array                          $localKey
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
     */
    protected function newHasOne(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        return new HasOne($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Validate the related model for Compoships compatibility.
     *
     * @param $related
     *
     * @throws InvalidUsageException
     */
    private function validateRelatedModel($related)
    {
        $traitClass = Compoships::class;
        if (!array_key_exists($traitClass, class_uses_recursive($related))) {
            throw new InvalidUsageException("The related model '{$related}' must use the '{$traitClass}' trait");
        }
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param string            $related
     * @param string|array|null $foreignKey
     * @param string|array|null $localKey
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        if (is_array($foreignKey)) { //Check for multi-columns relationship
            $this->validateRelatedModel($related);
        }

        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $foreignKeys = null;

        if (is_array($foreignKey)) { //Check for multi-columns relationship
            foreach ($foreignKey as $key) {
                $foreignKeys[] = $this->sanitizeKey($instance, $key);
            }
        } else {
            $foreignKey = $this->sanitizeKey($instance, $foreignKey);
        }

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany($instance->newQuery(), $this, $foreignKeys ?: $foreignKey, $localKey);
    }

    /**
     * Instantiate a new HasMany relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model   $parent
     * @param string|array                          $foreignKey
     * @param string|array                          $localKey
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasMany
     */
    protected function newHasMany(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        return new HasMany($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param string            $related
     * @param string|array|null $foreignKey
     * @param string|array|null $ownerKey
     * @param string            $relation
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        if (is_array($foreignKey)) { //Check for multi-columns relationship
            $this->validateRelatedModel($related);
        }

        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation).'_'.$instance->getKeyName();
        }

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Instantiate a new BelongsTo relationship.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Database\Eloquent\Model   $child
     * @param string|array                          $foreignKey
     * @param string|array                          $ownerKey
     * @param string                                $relation
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo
     */
    protected function newBelongsTo(Builder $query, Model $child, $foreignKey, $ownerKey, $relation)
    {
        return new BelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Honor DB::raw instances.
     *
     * @param string $instance
     * @param string $foreignKey
     *
     * @return string|Expression
     */
    protected function sanitizeKey($instance, $foreignKey)
    {
        $grammar = $this->getConnection()
            ->getQueryGrammar();

        return $grammar->isExpression($foreignKey)
            ? $foreignKey
            : $instance->getTable().'.'.$foreignKey;
    }
}
