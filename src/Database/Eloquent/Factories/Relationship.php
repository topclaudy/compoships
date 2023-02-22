<?php

namespace Awobaz\Compoships\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Relationship as EloquentRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;

class Relationship extends EloquentRelationship
{
    public function createFor(Model $parent)
    {
        $relationship = $parent->{$this->relationship}();

        if ($relationship instanceof MorphOneOrMany) {
            $this->factory->state([
                $relationship->getMorphType()      => $relationship->getMorphClass(),
                $relationship->getForeignKeyName() => $relationship->getParentKey(),
            ])->create([], $parent);
        } elseif ($relationship instanceof HasOneOrMany) { // This relationship is supported by Compoships. Check for multi-columns relationship.
            $this->factory->state(
                is_array($relationship->getForeignKeyName()) ?
                array_combine($relationship->getForeignKeyName(), $relationship->getParentKey()) :
                [$relationship->getForeignKeyName() => $relationship->getParentKey()]
            )->create([], $parent);
        } elseif ($relationship instanceof BelongsToMany) {
            $relationship->attach($this->factory->create([], $parent));
        }
    }
}
