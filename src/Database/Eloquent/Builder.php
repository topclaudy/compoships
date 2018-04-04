<?php

namespace Awobaz\Compoships\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder as BaseBuilder;;
use Awobaz\Compoships\Database\Eloquent\Concerns\QueriesRelationships;

class Builder extends BaseBuilder
{
    use QueriesRelationships;
}
