<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use Compoships;

    protected $guarded = [];

    public function teams()
    {
        return $this->belongsToMany(
            Team::class,
            'project_team',
            ['project_region_code', 'project_division_id'],
            ['team_region_code', 'team_division_id'],
            ['region_code', 'division_id'],
            ['region_code', 'division_id']
        );
    }
}
