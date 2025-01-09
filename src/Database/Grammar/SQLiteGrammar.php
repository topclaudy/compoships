<?php

namespace Awobaz\Compoships\Database\Grammar;

use Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber;
use Illuminate\Database\Query\Grammars\SQLiteGrammar as BaseSQLiteGrammar;

class SQLiteGrammar extends BaseSQLiteGrammar
{
    use CompileRowNumber;
}
