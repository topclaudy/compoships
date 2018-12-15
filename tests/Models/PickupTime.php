<?php

namespace Awobaz\Compoships\Tests\Model;

use Awobaz\Compoships\Database\Eloquent\Model;

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