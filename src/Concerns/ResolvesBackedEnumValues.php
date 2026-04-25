<?php

namespace Awobaz\Compoships\Concerns;

use BackedEnum;

trait ResolvesBackedEnumValues
{
    /**
     * Resolve a BackedEnum to its scalar value, or return the value as-is.
     */
    protected function resolveBackedEnumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
