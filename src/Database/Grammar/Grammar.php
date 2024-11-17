<?php

namespace Awobaz\Compoships\Database\Grammar;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;

class Grammar extends BaseGrammar
{
    protected function compileGroupLimit(Builder $query)
    {
        if (! is_array($query->groupLimit['column'])) {
            return parent::compileGroupLimit($query);
        }

        $selectBindings = array_merge($query->getRawBindings()['select'], $query->getRawBindings()['order']);

        $query->setBindings($selectBindings, 'select');
        $query->setBindings([], 'order');

        $limit = (int) $query->groupLimit['value'];
        $offset = $query->offset;

        if (isset($offset)) {
            $offset = (int) $offset;
            $limit += $offset;

            $query->offset = null;
        }

        $components = $this->compileComponents($query);

        $components['columns'] .= $this->compileRowNumber(
            $query->groupLimit['column'],
            $components['orders'] ?? ''
        );

        unset($components['orders']);

        $table = $this->wrap('laravel_table');
        $row = $this->wrap('laravel_row');

        $sql = $this->concatenate($components);

        $sql = 'select * from ('.$sql.') as '.$table.' where '.$row.' <= '.$limit;

        if (isset($offset)) {
            $sql .= ' and '.$row.' > '.$offset;
        }

        return $sql.' order by '.$row;
    }

    protected function compileRowNumber($partition, $orders)
    {
        if (! is_array($partition)) {
            return parent::compileRowNumber($partition, $orders);
        }

        $items = implode(
            ", ",
            array_map(fn ($p) => $this->wrap($p), $partition)
        );

        $over = trim('partition by '.$items.' '.$orders);

        return ', row_number() over ('.trim($over).') as '.$this->wrap('laravel_row');
    }
}
