<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int    $id
 * @property int    $booking_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Allocation[] $allocations
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class User extends Model
{
    use Compoships;

    // NOTE: we need this because Laravel 7 uses Carbon's method toJSON() instead of toDateTimeString()
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function allocations()
    {
        return $this->hasMany(Allocation::class, ['user_id', 'booking_id'], ['id', 'booking_id']);
    }
}
