<?php

namespace Awobaz\Compoships\Database\Query;

use Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Support\Arr;

class Builder extends BaseQueryBuilder
{
    /**
     * Add a "where in" clause to the query.
     *
     * @param \Illuminate\Contracts\Database\Query\Expression|string|string[] $column
     * @param mixed                                                           $values
     * @param string                                                          $boolean
     * @param bool                                                            $not
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     *
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if (!is_array($column)) {
            return parent::whereIn($column, $values, $boolean, $not);
        }

        $grammar = $this->getConnection()->getQueryGrammar();
        $inOperator = $not ? 'NOT IN' : 'IN';

        $columns = implode(', ', array_map(
            fn ($v) => $grammar->isExpression($v) ? $v->getValue($grammar) : $grammar->wrap($v),
            $column
        ));

        if ($this->isQueryable($values)) {
            [$sql, $bindings] = $this->createSub($values);

            return $this->whereRaw("({$columns}) {$inOperator} ({$sql})", $bindings, $boolean);
        }

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        // An empty tuple list would compile to invalid `IN ()` SQL on most
        // drivers, and a tuple whose arity differs from the column count
        // would expand into more (or fewer) placeholders than bindings:
        // silently NULL-filled on SQLite, a hard protocol error elsewhere.
        if (empty($values)) {
            return $this->whereRaw($not ? '1 = 1' : '0 = 1', [], $boolean);
        }

        foreach ($values as $tuple) {
            if (!is_array($tuple) || count($tuple) !== count($column)) {
                throw new InvalidUsageException(sprintf(
                    'Composite whereIn expects tuples of arity %d (columns: %s), got %s.',
                    count($column),
                    implode(', ', array_map(fn ($c) => $grammar->isExpression($c) ? $c->getValue($grammar) : $c, $column)),
                    is_array($tuple) ? 'a tuple of arity '.count($tuple) : get_debug_type($tuple)
                ));
            }
        }

        if ($this->getConnection()->getDriverName() === 'sqlsrv') {
            return $this->whereInTuplesAsPredicateGroup($column, $values, $boolean, $not);
        }

        $tuplePlaceholders = '('.implode(', ', array_fill(0, count($column), '?')).')';
        $placeholderList = implode(', ', array_fill(0, count($values), $tuplePlaceholders));

        $this->whereRaw("({$columns}) {$inOperator} ({$placeholderList})", Arr::flatten($values), $boolean);

        return $this;
    }

    /**
     * Compile a composite IN as an exact disjunction of per-tuple conjunctions,
     * for drivers without row-value IN support. Per-column IN lists would
     * match the cross product of the tuples, which is not the same predicate.
     *
     * @param array<int, string|\Illuminate\Contracts\Database\Query\Expression> $columns
     * @param array<int, array<int, mixed>>                                      $tuples
     * @param string                                                             $boolean
     * @param bool                                                               $not
     *
     * @return $this
     */
    protected function whereInTuplesAsPredicateGroup(array $columns, array $tuples, $boolean, $not)
    {
        $group = function ($query) use ($columns, $tuples) {
            foreach ($tuples as $tuple) {
                $query->orWhere(function ($conjunction) use ($columns, $tuple) {
                    foreach (array_values($columns) as $index => $column) {
                        $conjunction->where($column, '=', $tuple[$index]);
                    }
                });
            }
        };

        if (!$not) {
            return $this->whereNested($group, $boolean);
        }

        $nested = $this->forNestedWhere();
        $group($nested);

        $wheres = substr($this->getConnection()->getQueryGrammar()->compileWheres($nested), 6);

        return $this->whereRaw('not ('.$wheres.')', $nested->getBindings(), $boolean);
    }

    /**
     * Add a "group limit" clause to the query.
     *
     * Composite partitions need the package grammar's row-number compilation.
     * A connection whose custom grammar was preserved (see
     * Compoships::newBaseQueryBuilder()) cannot compile them, so fail with
     * guidance instead of a TypeError deep inside the grammar.
     *
     * @param int          $value
     * @param string|array $column
     *
     * @throws \Awobaz\Compoships\Exceptions\InvalidUsageException
     *
     * @return $this
     */
    public function groupLimit($value, $column)
    {
        if (is_array($column) && !in_array(CompileRowNumber::class, class_uses_recursive($this->grammar), true)) {
            throw new InvalidUsageException(sprintf(
                'Eager-load limit() on a composite relation needs a grammar that compiles composite row-number partitions, '.
                'but the connection uses %s. Extend the package grammar for your driver or use the %s trait in your grammar.',
                get_class($this->grammar),
                CompileRowNumber::class
            ));
        }

        return parent::groupLimit($value, $column);
    }

    public function whereColumn($first, $operator = null, $second = null, $boolean = 'and')
    {
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$second, $operator] = [$operator, '='];
        }

        // If the column and values are arrays, we will assume it is a multi-columns relationship
        // and compare the columns pairwise inside one group, so the caller's boolean
        // applies to the whole comparison rather than to each pair.
        if (is_array($first) || is_array($second)) {
            if (!is_array($first) || !is_array($second) || count($first) !== count($second)) {
                throw new InvalidUsageException(sprintf(
                    'Composite whereColumn expects two column lists of the same length, got %s and %s.',
                    is_array($first) ? count($first).' column(s)' : get_debug_type($first),
                    is_array($second) ? count($second).' column(s)' : get_debug_type($second)
                ));
            }

            $first = array_values($first);
            $second = array_values($second);

            return $this->whereNested(function ($query) use ($first, $operator, $second) {
                foreach ($first as $index => $column) {
                    $query->whereColumn($column, $operator, $second[$index]);
                }
            }, $boolean);
        }

        return parent::whereColumn($first, $operator, $second, $boolean);
    }
}
