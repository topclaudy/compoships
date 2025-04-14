<?php

namespace Awobaz\Compoships\Database\Grammar;

use Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber;
use Illuminate\Database\Query\Grammars\MariaDbGrammar as BaseMySqlGrammar;

class MariaDbGrammar extends BaseMySqlGrammar
{
    use CompileRowNumber;
}
