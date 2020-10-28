<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class User extends Model
{
    public function allocations()
    {
        return $this->hasMany(Allocation::class, ['user_id', 'booking_id'], ['id', 'booking_id']);
    }
}