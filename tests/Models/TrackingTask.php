<?php

namespace Awobaz\Compoships\Tests\Model;

use Awobaz\Compoships\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackingTask extends Model
{
    use SoftDeletes;

    public function allocation()
    {
        return $this->belongsTo(Allocation::class, ['booking_id', 'vehicle_id'], ['booking_id', 'vehicle_id']);
    }

    public function subTasks()
    {
        return $this->hasMany(TrackingTask::class, ['booking_id', 'vehicle_id'], ['booking_id', 'vehicle_id']);
    }
}