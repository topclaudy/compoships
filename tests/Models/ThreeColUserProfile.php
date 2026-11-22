<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

/**
 * Related model of a three column composite key relationship.
 *
 * @property int    $id
 * @property string $user_id
 * @property string $tenant_id
 * @property string $region_id
 * @property string $label
 * @property-read ThreeColUser $user
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class ThreeColUserProfile extends Model
{
    use Compoships;

    protected $table = 'three_col_user_profiles';

    public $timestamps = false;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(
            ThreeColUser::class,
            ['user_id', 'tenant_id', 'region_id'],
            ['id', 'tenant_id', 'region_id']
        );
    }
}
