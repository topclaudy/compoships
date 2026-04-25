<?php

namespace Awobaz\Compoships\Tests\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use Compoships;

    protected $guarded = [];

    /**
     * Touch propagation: when a Team::projects (or Team::projectsWithMeta)
     * pivot mutation occurs, Laravel's `touchIfTouching()` calls
     * `Project::touches('teams')` (the inverse relation name guessed from the
     * parent Team class basename). Returning true here causes the parent Team
     * to receive a `touch()` on attach/detach/sync. Required for the
     * BelongsToManyIntegrationTest touch propagation tests.
     */
    protected $touches = ['teams'];

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

    /**
     * Asymmetric mirror of User::projects(): composite foreign-pivot-key
     * (`['project_region_code', 'project_division_id']`) + scalar related-pivot-key
     * (`user_id`).
     */
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'project_user',
            ['project_region_code', 'project_division_id'],
            'user_id',
            ['region_code', 'division_id'],
            'id'
        );
    }
}
