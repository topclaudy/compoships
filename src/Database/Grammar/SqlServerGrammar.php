<?php

namespace Awobaz\Compoships\Database\Grammar;

use Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber;
use Illuminate\Database\Query\Grammars\SqlServerGrammar as BaseSqlServerGrammar;

class SqlServerGrammar extends BaseSqlServerGrammar
{
    use CompileRowNumber;
}
