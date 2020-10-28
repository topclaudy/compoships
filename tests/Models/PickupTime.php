<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PickupTime extends Model
{
    public function pickupPoint()
    {
        return $this->belongsTo(PickupPoint::class, ['contract_number', 'pickup_index'], [
            'contract_number',
            'pickup_index',
        ]);
    }
}