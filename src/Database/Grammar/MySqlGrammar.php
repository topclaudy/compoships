<?php

namespace Awobaz\Compoships\Database\Grammar;

use Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber;
use Illuminate\Database\Query\Grammars\MySqlGrammar as BaseMySqlGrammar;

class MySqlGrammar extends BaseMySqlGrammar
{
    use CompileRowNumber;
}
