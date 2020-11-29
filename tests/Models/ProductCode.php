<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $pcid
 * @property string $code
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ProductCode extends Model
{
    use Compoships;

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'product_codes';
}
