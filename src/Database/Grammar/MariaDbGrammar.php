<?php

namespace Awobaz\Compoships\Database\Grammar;

use Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber;
use Illuminate\Database\Query\Grammars\MariaDbGrammar as BaseMariaDbGrammar;

class MariaDbGrammar extends BaseMariaDbGrammar
{
    use CompileRowNumber;
}
