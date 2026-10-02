<?php

namespace Awobaz\Compoships\Database\Query;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Builder extends BaseQueryBuilder
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

            $inOperator = $not ? 'NOT IN' : 'IN';
            $prefix = $this->getConnection()->getTablePrefix();
            $grammar = $this->getConnection()->getQueryGrammar();

            foreach ($column as &$value) {
                if (!$grammar->isExpression($value) && !Str::contains($value, '.')) {
                    $value = $prefix.$value;
                }
            }

            if ($this->getConnection()->getDriverName() === 'sqlsrv') {
                foreach ($column as $column_number => $column_name) {
                    $column_values = array_unique(Arr::pluck($values, $column_number));
                    $values_placeholders = implode(', ', array_fill(0, count($column_values), '?'));

                    $this->whereRaw("{$column_name} {$inOperator} ({$values_placeholders})", Arr::flatten($column_values), $boolean);
                }

                return $this;
            }

            $columns = implode(', ', array_map(
                fn ($v) => $grammar->isExpression($v) ? $v->getValue($grammar) : $grammar->wrap($v),
                $column
            ));
            $tuplePlaceholders = '('.implode(', ', array_fill(0, count($column), '?')).')';
            $placeholderList = implode(', ', array_fill(0, count($values), $tuplePlaceholders));

            $this->whereRaw("({$columns}) {$inOperator} ({$placeholderList})", Arr::flatten($values), $boolean);

            return $this;
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
