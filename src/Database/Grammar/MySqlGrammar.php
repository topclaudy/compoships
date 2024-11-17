<?php

namespace Awobaz\Compoships\Database\Grammar;

use Illuminate\Database\Query\Grammars\MySqlGrammar as BaseMySqlGrammar;

class MySqlGrammar extends BaseMySqlGrammar
{
    protected function compileRowNumber($partition, $orders)
    {
        if (! is_array($partition)) {
            return parent::compileRowNumber($partition, $orders);
        }

        $columns = implode(
            ", ",
            array_map(function ($column) {
                return $this->wrap($column);
            }, $partition)
        );

        $over = trim('partition by '.$columns.' '.$orders);

        return ', row_number() over ('.trim($over).') as '.$this->wrap('laravel_row');
    }
}
