<?php

namespace Awobaz\Compoships\Tests\Model;

use Awobaz\Compoships\Database\Eloquent\Model;

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