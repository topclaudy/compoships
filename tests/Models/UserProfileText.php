<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Awobaz\Compoships\Tests\Enums\UserProfileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int             $user_id
 * @property UserProfileType $user_profile_type
 * @property string          $user_profile_text
 * @property Carbon          $created_at
 * @property Carbon          $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class UserProfileText extends Model
{
    use Compoships;

    // NOTE: we need this because Laravel 7 uses Carbon's method toJSON() instead of toDateTimeString()
    protected $casts = [
        'created_at'        => 'datetime:Y-m-d H:i:s',
        'updated_at'        => 'datetime:Y-m-d H:i:s',
        'user_profile_type' => UserProfileType::class,
    ];

    public function userProfile()
    {
        return $this->belongsTo(UserProfile::class, ['user_id', 'user_profile_type'], ['user_id', 'user_profile_type']);
    }
}
