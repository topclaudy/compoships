<?php

namespace Awobaz\Compoships\Concerns;

/**
 * Adds eager-load constraints for a set of composite key tuples.
 *
 * Null-free tuples compile to one tuple IN clause. Tuples with a null
 * component cannot be matched by IN (SQL never equates NULL), so each becomes
 * a nested group of equality and IS NULL predicates, keeping eager loading
 * consistent with the lazy path. Tuples that are entirely null can match
 * nothing and are dropped.
 *
 * Requires the using class to provide resolveBackedEnumValue() and
 * compositeDictionaryKey() (see ResolvesBackedEnumValues).
 */
trait BuildsCompositeEagerConstraints
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     * @param array<int, string|\Illuminate\Contracts\Database\Query\Expression>     $columns
     * @param array<int, array<int, mixed>>                                            $tuples
     *
     * @return bool whether any constraint was added
     */
    protected function addCompositeKeyConstraints($query, array $columns, array $tuples): bool
    {
        [$nullFree, $withNulls] = $this->partitionCompositeKeyTuples($tuples);

        if ($nullFree === [] && $withNulls === []) {
            return false;
        }

        if ($withNulls === []) {
            $query->whereIn($columns, $nullFree);

            return true;
        }

        $query->where(function ($nested) use ($columns, $nullFree, $withNulls) {
            if ($nullFree !== []) {
                $nested->whereIn($columns, $nullFree);
            }

            foreach ($withNulls as $tuple) {
                $nested->orWhere(function ($group) use ($columns, $tuple) {
                    foreach ($columns as $index => $column) {
                        $tuple[$index] === null
                            ? $group->whereNull($column)
                            : $group->where($column, '=', $tuple[$index]);
                    }
                });
            }
        });

        return true;
    }

    /**
     * Resolve enums, drop all-null tuples, deduplicate by normalised value, and
     * split the remainder into null-free and null-containing tuples.
     *
     * @param array<int, array<int, mixed>> $tuples
     *
     * @return array{0: array<int, array<int, mixed>>, 1: array<int, array<int, mixed>>}
     */
    protected function partitionCompositeKeyTuples(array $tuples): array
    {
        $nullFree = [];
        $withNulls = [];
        $seen = [];

        foreach ($tuples as $tuple) {
            $tuple = array_map(fn ($value) => $this->resolveBackedEnumValue($value), array_values($tuple));

            $nonNull = array_filter($tuple, fn ($value) => $value !== null);

            if ($nonNull === []) {
                continue;
            }

            $key = $this->compositeDictionaryKey($tuple);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            if (count($nonNull) === count($tuple)) {
                $nullFree[] = $tuple;
            } else {
                $withNulls[] = $tuple;
            }
        }

        return [$nullFree, $withNulls];
    }
}
