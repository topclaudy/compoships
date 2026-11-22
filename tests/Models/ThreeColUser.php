<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

class ThreeColUser extends Model
{
    use Compoships;

    protected $table = 'three_col_users';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $compositeKey = ['id', 'tenant_id', 'region_id'];

    /**
     * @return \Awobaz\Compoships\Database\Eloquent\Relations\HasMany
     */
    public function profiles()
    {
        return $this->hasMany(
            ThreeColUserProfile::class,
            ['user_id', 'tenant_id', 'region_id'],
            ['id', 'tenant_id', 'region_id']
        );
    }
}
