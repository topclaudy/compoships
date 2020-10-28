<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class PickupPoint extends Model
{
    public function pickupTimes()
    {
        return $this->hasMany(PickupTime::class, ['contract_number', 'pickup_index'], [
            'contract_number',
            'pickup_index',
        ]);
    }
}