<?php

namespace Awobaz\Compoships\Database\Query;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait Compoships
{
    /**
     * Split Oracle IN lists because Oracle accepts at most 1000 expressions
     * in a single IN clause.
     *
     * @return $this
     */
    public function whereInOracle($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'NotIn' : 'In';

        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        if (is_array($values) && count($values) > 1000) {
            $chunks = array_chunk($values, 1000);

            return $this->where(function ($query) use ($column, $chunks, $type, $not) {
                foreach ($chunks as $ch) {
                    $sqlClause = $not ? 'where'.$type : 'orWhere'.$type;
                    $query->{$sqlClause}($column, $ch);
                }
            }, null, null, $boolean);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

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
        // Here we implement custom support for multi-column 'IN'
        if (is_array($column)) {
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
                        implode(', ', $column),
                        var_export($tuple, true)
                    ));
                }
            }

            $connection = $this->getConnection();
            $prefix = $connection->getTablePrefix();
            $grammar = $connection->getQueryGrammar();

            foreach ($column as &$value) {
                if (!$grammar->isExpression($value) && !Str::contains($value, '.')) {
                    $value = $prefix.$value;
                }
            }

            if (
                in_array($connection->getDriverName(), ['sqlsrv']) ||
                    Arr::some($values, fn ($value) => in_array(null, $value, true))
            ) {
                // use a series of OR/AND clauses when optimized row value expressions can't be used
                return $this->where(function ($query) use ($column, $values) {
                    foreach ($values as $value) {
                        $query->orWhere(function ($query) use ($column, $value) {
                            foreach ($column as $index => $aColumn) {
                                $query->where($aColumn, $value[$index]);
                            }
                        });
                    }
                }, null, null, $boolean.($not ? ' not' : ''));
            }

            $inOperator = $not ? 'NOT IN' : 'IN';
            $columns = implode(', ', array_map(
                fn ($v) => $grammar->isExpression($v) ? $v->getValue($grammar) : $grammar->wrap($v),
                $column
            ));
            $tuplePlaceholders = '('.implode(', ', array_fill(0, count($column), '?')).')';
            $placeholderList = implode(', ', array_fill(0, count($values), $tuplePlaceholders));

            return $this->whereRaw("({$columns}) {$inOperator} ({$placeholderList})", Arr::flatten($values), $boolean);
        }

        if ($this->getConnection()->getDriverName() === 'oracle') {
            return $this->whereInOracle($column, $values, $boolean, $not);
        }

        return parent::whereIn($column, $values, $boolean, $not);
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
        // and we adjust the 'where' clauses accordingly
        if (is_array($first) && is_array($second)) {
            $type = 'Column';

            foreach ($first as $index => $f) {
                $this->wheres[] = [
                    'type'     => $type,
                    'first'    => $f,
                    'operator' => $operator,
                    'second'   => $second[$index],
                    'boolean'  => $boolean,

                ];
            }

            return $this;
        }

        return parent::whereColumn($first, $operator, $second, $boolean);
    }
}
