<?php

namespace Awobaz\Compoships\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

/**
 * A model that does not use the package, for validation tests.
 */
class PlainModel extends Model
{
    protected $table = 'projects';
}
