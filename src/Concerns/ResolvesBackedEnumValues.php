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

    /**
     * Build an unambiguous dictionary key for a composite key tuple.
     *
     * Components are normalised the way PDO and bindings would present them
     * (enums to their backing value, scalars to strings, booleans to "1"/"0")
     * so in-memory and database-loaded values produce the same key, while
     * null stays distinct from the empty string and values containing the
     * separator cannot collide.
     *
     * @param array<int, mixed> $values
     */
    protected function compositeDictionaryKey(array $values): string
    {
        $normalized = array_map(function (mixed $value): ?string {
            $value = $this->resolveBackedEnumValue($value);

            if ($value === null) {
                return null;
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return (string) $value;
        }, array_values($values));

        return json_encode($normalized);
    }
}
