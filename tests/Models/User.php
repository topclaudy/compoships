<?php

namespace Awobaz\Compoships\Tests\Model;

use Awobaz\Compoships\Database\Eloquent\Model;

class User extends Model
{
    public function allocations()
    {
        return $this->hasMany(Allocation::class, ['user_id', 'booking_id'], ['id', 'booking_id']);
    }
}