<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $contract_number
 * @property int    $pickup_index
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PickupTime[] $pickupTimes
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PickupPoint extends Model
{
    use Compoships;

    // NOTE: we need this because Laravel 7 uses Carbon's method toJSON() instead of toDateTimeString()
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function pickupTimes()
    {
        return $this->hasMany(PickupTime::class, ['contract_number', 'pickup_index'], [
            'contract_number',
            'pickup_index',
        ]);
    }
}
