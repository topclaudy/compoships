<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Tests\Factories\AllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int    $id
 * @property int    $user_id
 * @property int    $booking_id
 * @property int    $vehicle_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TrackingTask[]|Collection $trackingTasks
 * @property-read OriginalPackage[]|Collection $originalPackages
 * @property-read Space $space
 * @property-read User $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Allocation extends Model
{
    use Compoships;
    use HasFactory;

    // NOTE: we need this because Laravel 7 uses Carbon's method toJSON() instead of toDateTimeString()
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasMany
     */
    public function trackingTasks()
    {
        return $this->hasMany(TrackingTask::class, ['booking_id', 'vehicle_id'], ['booking_id', 'vehicle_id']);
    }

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasMany
     */
    public function originalPackages()
    {
        return $this->hasMany(OriginalPackage::class);
    }

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
     */
    public function originalPackagesOneOfMany()
    {
        return $this->hasOne(OriginalPackage::class)->ofMany();
    }

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
     */
    public function space()
    {
        return $this->hasOne(Space::class, 'booking_id', 'booking_id');
    }

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, ['user_id', 'booking_id'], ['id', 'booking_id']);
    }

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
     */
    public function smallerTrackingTask()
    {
        $rel = $this->hasOne(TrackingTask::class, ['booking_id', 'vehicle_id'], ['booking_id', 'vehicle_id']);

        return getLaravelVersion() < 8 ? $rel : $rel->ofMany('id', 'min');
    }

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
     */
    public function latestTrackingTask()
    {
        $rel = $this->hasOne(TrackingTask::class, ['booking_id', 'vehicle_id'], ['booking_id', 'vehicle_id']);

        return getLaravelVersion() < 8 ? $rel : $rel->latestOfMany();
    }

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasOne
     */
    public function oldestTrackingTask()
    {
        $rel = $this->hasOne(TrackingTask::class, ['booking_id', 'vehicle_id'], ['booking_id', 'vehicle_id']);

        return getLaravelVersion() < 8 ? $rel : $rel->oldestOfMany();
    }

    protected static function newFactory()
    {
        return AllocationFactory::new();
    }
}
