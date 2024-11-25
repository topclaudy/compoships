<?php

namespace Awobaz\Compoships\Database\Grammar\Concerns;

trait CompileRowNumber
{
    protected function compileRowNumber($partition, $orders)
    {
        if (!is_array($partition)) {
            return parent::compileRowNumber($partition, $orders);
        }

        $columns = implode(', ', array_map(fn ($p) => $this->wrap($p), $partition));

        $over = trim('partition by '.$columns.' '.$orders);

        return ', row_number() over ('.$over.') as '.$this->wrap('laravel_row');
    }
}
