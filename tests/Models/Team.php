<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use Compoships;

    protected $guarded = [];

    public function projects()
    {
        return $this->belongsToMany(
            Project::class,
            'project_team',
            ['team_region_code', 'team_division_id'],
            ['project_region_code', 'project_division_id'],
            ['region_code', 'division_id'],
            ['region_code', 'division_id']
        );
    }

    public function projectsWithMeta()
    {
        return $this->belongsToMany(
            Project::class,
            'project_team',
            ['team_region_code', 'team_division_id'],
            ['project_region_code', 'project_division_id'],
            ['region_code', 'division_id'],
            ['region_code', 'division_id']
        )
            ->withPivot('role')
            ->withTimestamps();
    }

    public function projectsWithPivotModel()
    {
        return $this->belongsToMany(
            Project::class,
            'project_team',
            ['team_region_code', 'team_division_id'],
            ['project_region_code', 'project_division_id'],
            ['region_code', 'division_id'],
            ['region_code', 'division_id']
        )->using(ProjectTeamPivot::class);
    }

    public function projectsWithEnumPivot()
    {
        return $this->belongsToMany(
            Project::class,
            'project_team',
            ['team_region_code', 'team_division_id'],
            ['project_region_code', 'project_division_id'],
            ['region_code', 'division_id'],
            ['region_code', 'division_id']
        )->using(ProjectTeamEnumPivot::class);
    }
}
