<?php

namespace Awobaz\Compoships\Contracts;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\ConnectionInterface;

interface BuilderResolver
{
    public static function resolve(ConnectionInterface $connection): ?Builder;
}
