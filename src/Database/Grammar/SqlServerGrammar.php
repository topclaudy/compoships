<?php

namespace Awobaz\Compoships\Database\Grammar;

use Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber;
use Illuminate\Database\Query\Grammars\SqlServerGrammar as BaseSqlServerGrammar;

class SqlServerGrammar extends BaseSqlServerGrammar
{
    use CompileRowNumber {
        compileRowNumber as compileCompositeRowNumber;
    }

    /**
     * SQL Server requires an ORDER BY inside ROW_NUMBER(); Laravel's grammar
     * adds the `(select 0)` fallback only on its own scalar path, so the
     * composite partition needs it applied before delegating.
     *
     * @param string|array $partition
     * @param string       $orders
     *
     * @return string
     */
    protected function compileRowNumber($partition, $orders)
    {
        if (is_array($partition) && empty($orders)) {
            $orders = 'order by (select 0)';
        }

        return $this->compileCompositeRowNumber($partition, $orders);
    }
}
