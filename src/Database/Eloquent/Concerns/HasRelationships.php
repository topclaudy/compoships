<?php

namespace Awobaz\Compoships\Database\Eloquent\Concerns;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Database\Eloquent\Relations\HasMany;
use Awobaz\Compoships\Database\Eloquent\Relations\HasOne;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
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
     * @param  string $related
     * @param  string|array|null $foreignKey
     * @param  string|array|null $localKey
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
                $foreignKeys[] = $this->maybeExpression($instance, $key);
            }
        } else {
            $foreignKey = $this->maybeExpression($instance, $foreignKey);
        }

        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKeys ?: $foreignKey, $localKey);
    }

    /**
     * Validate the related model for Compoships compatibility
     *
     * @param  $related
     * @return void
     * @throws InvalidUsageException
     */
    private function validateRelatedModel($related)
    {
        $uses = class_uses_recursive($related);

        if (! array_key_exists('Awobaz\Compoships\Compoships', $uses) && ! is_subclass_of($related, 'Awobaz\Compoships\Database\Eloquent\Model')) {
            throw new InvalidUsageException("The related model '${related}' must either extend 'Awobaz\Compoships\Database\Eloquent\Model' or use the 'Awobaz\Compoships\Compoships' trait");
        }
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string $related
     * @param  string|array|null $foreignKey
     * @param  string|array|null $localKey
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
                $foreignKeys[] = $this->maybeExpression($instance, $key);
            }
        } else {
            $foreignKey = $this->maybeExpression($instance, $foreignKey);
        }

        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKeys ?: $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string $related
     * @param  string|array|null $foreignKey
     * @param  string|array|null $ownerKey
     * @param  string $relation
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

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Honor DB::raw instances
     *
     * @param  string $instance
     * @param  string $foreignKey
     * @return string|Expression
     */
    protected function maybeExpression($instance, $foreignKey)
    {
        $grammar = $this->getConnection()->getQueryGrammar();

        return $grammar->isExpression($foreignKey) 
            ? DB::raw($instance->getTable().'.'.$foreignKey)
            : $instance->getTable().'.'.$foreignKey;
    }
}
