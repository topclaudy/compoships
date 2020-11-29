<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $contract_number
 * @property int    $pickup_index
 * @property string $days
 * @property string $pickup_time
 * @property-read PickupPoint $pickupPoint
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PickupTime extends Model
{
    use Compoships;

    public function pickupPoint()
    {
        return $this->belongsTo(PickupPoint::class, ['contract_number', 'pickup_index'], [
            'contract_number',
            'pickup_index',
        ]);
    }
}
