<?php

namespace Awobaz\Compoships\Database\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

trait ComposhipsFactory
{
    public function has(EloquentFactory $factory, $relationship = null)
    {
        return $this->newInstance([
            'has' => $this->has->concat([new Relationship(
                $factory,
                $relationship ?? $this->guessRelationship($factory->modelName())
            )]),
        ]);
    }
}
