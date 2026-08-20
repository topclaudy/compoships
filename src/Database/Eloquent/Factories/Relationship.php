<?php

namespace Awobaz\Compoships\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Relationship as EloquentRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;

class Relationship extends EloquentRelationship
{
    /**
     * Create the child relationship for the given parent model.
     *
     * Mirrors Laravel's implementation, with composite foreign keys spread
     * over every column and the relation's pending attributes (withAttributes)
     * applied where the installed Laravel version supports prependState().
     *
     * @param \Illuminate\Database\Eloquent\Model $parent
     *
     * @return void
     */
    public function createFor(Model $parent)
    {
        $relationship = $parent->{$this->relationship}();

        if ($relationship instanceof MorphOneOrMany) {
            $this->withPendingAttributes($this->factory->state([
                $relationship->getMorphType()      => $relationship->getMorphClass(),
                $relationship->getForeignKeyName() => $relationship->getParentKey(),
            ]), $relationship)->create([], $parent);
        } elseif ($relationship instanceof HasOneOrMany) { // This relationship is supported by Compoships. Check for multi-columns relationship.
            $this->withPendingAttributes($this->factory->state(
                is_array($relationship->getForeignKeyName()) ?
                array_combine($relationship->getForeignKeyName(), $relationship->getParentKey()) :
                [$relationship->getForeignKeyName() => $relationship->getParentKey()]
            ), $relationship)->create([], $parent);
        } elseif ($relationship instanceof BelongsToMany) {
            $relationship->attach($this->withPendingAttributes($this->factory, $relationship)->create([], $parent));
        }
    }

    /**
     * Apply the relation's pending attributes below the factory's own state.
     * Factory::prependState() exists from illuminate/database 12.15; older
     * versions simply do not support withAttributes() in factories.
     */
    protected function withPendingAttributes(Factory $factory, Relation $relationship): Factory
    {
        if (!method_exists($factory, 'prependState')) {
            return $factory;
        }

        return $factory->prependState($relationship->getQuery()->pendingAttributes);
    }
}
