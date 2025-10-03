<?php

namespace Awobaz\Compoships\Database\Grammar;

use Awobaz\Compoships\Database\Grammar\Concerns\CompileRowNumber;
use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;

class PostgresGrammar extends BasePostgresGrammar
{
    use CompileRowNumber;
}
