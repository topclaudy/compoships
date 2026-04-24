<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use Compoships;

    protected $guarded = [];
}
