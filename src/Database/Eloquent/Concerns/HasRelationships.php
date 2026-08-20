<?php

namespace Awobaz\Compoships\Database\Eloquent\Concerns;

use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany;
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
            return array_map(fn ($key) => $this->qualifyColumn($key), $keyName);
        }

        return parent::getQualifiedKeyName();
    }

    /**
     * Define a one-to-one relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TRelatedModel> $related
     * @param string|array|null           $foreignKey
     * @param string|array|null           $localKey
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne<TRelatedModel, $this>
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

        $this->validateCompositeKeyPair('hasOne', 'foreign key', $foreignKeys ?: $foreignKey, 'local key', $localKey);

        return $this->newHasOne($instance->newQuery(), $this, $foreignKeys ?: $foreignKey, $localKey);
    }

    /**
     * Instantiate a new HasOne relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query
     * @param TDeclaringModel                                      $parent
     * @param string|array                                         $foreignKey
     * @param string|array                                         $localKey
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne<TRelatedModel, TDeclaringModel>
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
     * When either side of a key pair is an array, both must be arrays of the
     * same length; anything else silently binds nulls or emits PHP warnings.
     *
     * @param string       $relationType
     * @param string       $firstName
     * @param array|string $first
     * @param string       $secondName
     * @param array|string $second
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     */
    private function validateCompositeKeyPair($relationType, $firstName, $first, $secondName, $second): void
    {
        if (!is_array($first) && !is_array($second)) {
            return;
        }

        if (is_array($first) && is_array($second) && count($first) === count($second)) {
            return;
        }

        $describe = static fn ($keys) => is_array($keys)
            ? '['.implode(', ', array_map(static fn ($k) => is_string($k) ? $k : get_debug_type($k), $keys)).']'
            : var_export($keys, true);

        throw new InvalidUsageException(sprintf(
            'Composite %s() on %s requires the %s and the %s to be arrays of the same length, got %s and %s.',
            $relationType,
            static::class,
            $firstName,
            $secondName,
            $describe($first),
            $describe($second)
        ));
    }

    /**
     * Define a one-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TRelatedModel> $related
     * @param string|array|null           $foreignKey
     * @param string|array|null           $localKey
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasMany<TRelatedModel, $this>
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

        $this->validateCompositeKeyPair('hasMany', 'foreign key', $foreignKeys ?: $foreignKey, 'local key', $localKey);

        return $this->newHasMany($instance->newQuery(), $this, $foreignKeys ?: $foreignKey, $localKey);
    }

    /**
     * Instantiate a new HasMany relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query
     * @param TDeclaringModel                                      $parent
     * @param string|array                                         $foreignKey
     * @param string|array                                         $localKey
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasMany<TRelatedModel, TDeclaringModel>
     */
    protected function newHasMany(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        return new HasMany($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TRelatedModel> $related
     * @param string|array|null           $foreignKey
     * @param string|array|null           $ownerKey
     * @param string                      $relation
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo<TRelatedModel, $this>
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

        $this->validateCompositeKeyPair('belongsTo', 'foreign key', $foreignKey, 'owner key', $ownerKey);

        return $this->newBelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Instantiate a new BelongsTo relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query
     * @param TDeclaringModel                                      $child
     * @param string|array                                         $foreignKey
     * @param string|array                                         $ownerKey
     * @param string                                               $relation
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo<TRelatedModel, TDeclaringModel>
     */
    protected function newBelongsTo(Builder $query, Model $child, $foreignKey, $ownerKey, $relation)
    {
        return new BelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Define a many-to-many relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     *
     * @param class-string<TRelatedModel> $related
     * @param string|null                 $table
     * @param string|array|null           $foreignPivotKey
     * @param string|array|null           $relatedPivotKey
     * @param string|array|null           $parentKey
     * @param string|array|null           $relatedKey
     * @param string|null                 $relation
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, $this>
     */
    public function belongsToMany(
        $related,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null
    ) {
        if (is_array($foreignPivotKey) || is_array($relatedPivotKey)) {
            $this->validateRelatedModel($related);
        }

        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        if (is_null($table)) {
            $table = $this->joiningTable($related, $instance);
        }

        return $this->newBelongsToMany(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation
        );
    }

    /**
     * Instantiate a new BelongsToMany relationship.
     *
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param \Illuminate\Database\Eloquent\Builder<TRelatedModel> $query
     * @param TDeclaringModel                                      $parent
     * @param string                                               $table
     * @param string|array                                         $foreignPivotKey
     * @param string|array                                         $relatedPivotKey
     * @param string|array                                         $parentKey
     * @param string|array                                         $relatedKey
     * @param string|null                                          $relationName
     *
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany<TRelatedModel, TDeclaringModel>
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        return new BelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
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

        return $grammar->isExpression($foreignKey) || Str::contains($foreignKey, '.')
            ? $foreignKey
            : $instance->getTable().'.'.$foreignKey;
    }
}
