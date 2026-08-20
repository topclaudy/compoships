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

    /**
     * Same pivot as projectsWithMeta() with the division column first, so a
     * scalar id maps onto project_division_id.
     */
    public function projectsByDivision()
    {
        return $this->belongsToMany(
            Project::class,
            'project_team',
            ['team_region_code', 'team_division_id'],
            ['project_division_id', 'project_region_code'],
            ['region_code', 'division_id'],
            ['division_id', 'region_code']
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

    public function projectsWithPivotModelNoId()
    {
        return $this->belongsToMany(
            Project::class,
            'project_team_no_id',
            ['team_region_code', 'team_division_id'],
            ['project_region_code', 'project_division_id'],
            ['region_code', 'division_id'],
            ['region_code', 'division_id']
        )->using(ProjectTeamNoIdPivot::class);
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
