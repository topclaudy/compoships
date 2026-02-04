<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Tests\Enums\UserProfileType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int             $user_id
 * @property UserProfileType $user_profile_type
 * @property Carbon          $created_at
 * @property Carbon          $updated_at
 * @property-read Collection<UserProfileText> $userProfileTexts
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class UserProfile extends Model
{
    use Compoships;

    // NOTE: we need this because Laravel 7 uses Carbon's method toJSON() instead of toDateTimeString()
    protected $casts = [
        'created_at'        => 'datetime:Y-m-d H:i:s',
        'updated_at'        => 'datetime:Y-m-d H:i:s',
        'user_profile_type' => UserProfileType::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userProfileTexts()
    {
        return $this->hasMany(UserProfileText::class, ['user_id', 'user_profile_type'], ['user_id', 'user_profile_type']);
    }
}
