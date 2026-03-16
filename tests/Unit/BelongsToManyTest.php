<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\ProjectTeamPivot;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany
 * @covers \Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships::belongsToMany
 * @covers \Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships::newBelongsToMany
 */
class BelongsToManyTest extends TestCase
{
    public function test_basic_relationship_loading()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('US', 1, 'Website');

        $this->attachPivot($team, $project);

        $loadedProjects = $team->projects;

        $this->assertCount(1, $loadedProjects);
        $this->assertEquals($project->id, $loadedProjects->first()->id);
    }

    public function test_inverse_relationship_loading()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('US', 1, 'Website');

        $this->attachPivot($team, $project);

        $loadedTeams = $project->teams;

        $this->assertCount(1, $loadedTeams);
        $this->assertEquals($team->id, $loadedTeams->first()->id);
    }

    public function test_multiple_results()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'Mobile');

        $this->attachPivot($team, $project1);
        $this->attachPivot($team, $project2);

        $loadedProjects = $team->projects;

        $this->assertCount(2, $loadedProjects);
    }

    public function test_no_cross_contamination_between_composite_keys()
    {
        $team1 = $this->createTeam('US', 1, 'Alpha');
        $team2 = $this->createTeam('EU', 2, 'Beta');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $this->attachPivot($team1, $project1);
        $this->attachPivot($team2, $project2);

        $team1Projects = $team1->projects;
        $team2Projects = $team2->projects;

        $this->assertCount(1, $team1Projects);
        $this->assertEquals('Website', $team1Projects->first()->name);

        $this->assertCount(1, $team2Projects);
        $this->assertEquals('API', $team2Projects->first()->name);
    }

    public function test_eager_loading()
    {
        $team1 = $this->createTeam('US', 1, 'Alpha');
        $team2 = $this->createTeam('EU', 2, 'Beta');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $this->attachPivot($team1, $project1);
        $this->attachPivot($team2, $project2);

        $teams = Team::with('projects')->get();

        $this->assertCount(2, $teams);
        $this->assertCount(1, $teams[0]->projects);
        $this->assertCount(1, $teams[1]->projects);
        $this->assertEquals('Website', $teams[0]->projects->first()->name);
        $this->assertEquals('API', $teams[1]->projects->first()->name);
    }

    public function test_eager_loading_with_no_results()
    {
        $team = $this->createTeam('US', 1, 'Alpha');

        $teams = Team::with('projects')->get();

        $this->assertCount(1, $teams);
        $this->assertCount(0, $teams[0]->projects);
    }

    public function test_attach_with_model()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('US', 1, 'Website');

        $team->projects()->attach($project);

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('US', $pivot['team_region_code']);
        $this->assertEquals((string) 1, $pivot['team_division_id']);
        $this->assertEquals('US', $pivot['project_region_code']);
        $this->assertEquals((string) 1, $pivot['project_division_id']);
    }

    public function test_attach_with_composite_id_array()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach([['EU', 2]]);

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('US', $pivot['team_region_code']);
        $this->assertEquals((string) 1, $pivot['team_division_id']);
        $this->assertEquals('EU', $pivot['project_region_code']);
        $this->assertEquals((string) 2, $pivot['project_division_id']);
    }

    public function test_attach_multiple_models()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projects()->attach([$project1, $project2]);

        $this->assertEquals(2, Capsule::table('project_team')->count());
    }

    public function test_detach_specific_model()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projects()->attach([$project1, $project2]);
        $this->assertEquals(2, Capsule::table('project_team')->count());

        $team->projects()->detach($project1);
        $this->assertEquals(1, Capsule::table('project_team')->count());

        $remaining = $team->fresh()->projects;
        $this->assertCount(1, $remaining);
        $this->assertEquals('API', $remaining->first()->name);
    }

    public function test_detach_all()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projects()->attach([$project1, $project2]);
        $this->assertEquals(2, Capsule::table('project_team')->count());

        $team->projects()->detach();
        $this->assertEquals(0, Capsule::table('project_team')->count());
    }

    public function test_sync()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');
        $project3 = $this->createProject('AP', 3, 'Mobile');

        $team->projects()->attach([$project1, $project2]);
        $this->assertEquals(2, Capsule::table('project_team')->count());

        $changes = $team->projects()->sync([$project2, $project3]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);

        $remaining = $team->fresh()->projects;
        $this->assertCount(2, $remaining);
        $names = $remaining->pluck('name')->sort()->values()->all();
        $this->assertEquals(['API', 'Mobile'], $names);
    }

    public function test_sync_without_detaching()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projects()->attach($project1);

        $changes = $team->projects()->syncWithoutDetaching([$project2]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(0, $changes['detached']);
        $this->assertCount(2, $team->fresh()->projects);
    }

    public function test_toggle()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projects()->attach($project1);

        $changes = $team->projects()->toggle([$project1, $project2]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);

        $remaining = $team->fresh()->projects;
        $this->assertCount(1, $remaining);
        $this->assertEquals('API', $remaining->first()->name);
    }

    public function test_has()
    {
        $team1 = $this->createTeam('US', 1, 'Alpha');
        $team2 = $this->createTeam('EU', 2, 'Beta');
        $project = $this->createProject('US', 1, 'Website');

        $this->attachPivot($team1, $project);

        $teamsWithProjects = Team::has('projects')->get();

        $this->assertCount(1, $teamsWithProjects);
        $this->assertEquals('Alpha', $teamsWithProjects->first()->name);
    }

    public function test_where_has()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('US', 1, 'API');

        $this->attachPivot($team, $project1);
        $this->attachPivot($team, $project2);

        $teams = Team::whereHas('projects', function ($query) {
            $query->where('name', 'Website');
        })->get();

        $this->assertCount(1, $teams);
    }

    public function test_with_pivot()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('US', 1, 'Website');

        Capsule::table('project_team')->insert([
            'team_region_code'    => 'US',
            'team_division_id'    => 1,
            'project_region_code' => 'US',
            'project_division_id' => 1,
            'role'                => 'lead',
        ]);

        $loadedProjects = $team->projects()->withPivot('role')->get();

        $this->assertCount(1, $loadedProjects);
        $this->assertEquals('lead', $loadedProjects->first()->pivot->role);
    }

    public function test_with_timestamps()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('US', 1, 'Website');

        $team->projects()->withTimestamps()->attach($project);

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertNotNull($pivot['created_at']);
        $this->assertNotNull($pivot['updated_at']);
    }

    public function test_null_parent_keys_return_empty_collection()
    {
        $team = new Team();
        $team->region_code = null;
        $team->division_id = null;
        $team->name = 'Orphan';
        $team->save();

        $projects = $team->projects;

        $this->assertCount(0, $projects);
    }

    public function test_custom_pivot_model_attach()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('US', 1, 'Website');

        $team->projectsWithPivotModel()->attach($project);

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('US', $pivot['team_region_code']);
        $this->assertEquals((string) 1, $pivot['team_division_id']);
        $this->assertEquals('US', $pivot['project_region_code']);
        $this->assertEquals((string) 1, $pivot['project_division_id']);
    }

    public function test_custom_pivot_model_detach()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projectsWithPivotModel()->attach([$project1, $project2]);
        $this->assertEquals(2, Capsule::table('project_team')->count());

        $team->projectsWithPivotModel()->detach($project1);
        $this->assertEquals(1, Capsule::table('project_team')->count());

        $remaining = $team->fresh()->projectsWithPivotModel;
        $this->assertCount(1, $remaining);
        $this->assertEquals('API', $remaining->first()->name);
    }

    public function test_custom_pivot_model_detach_all()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projectsWithPivotModel()->attach([$project1, $project2]);
        $this->assertEquals(2, Capsule::table('project_team')->count());

        $team->projectsWithPivotModel()->detach();
        $this->assertEquals(0, Capsule::table('project_team')->count());
    }

    public function test_custom_pivot_model_sync()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');
        $project3 = $this->createProject('AP', 3, 'Mobile');

        $team->projectsWithPivotModel()->attach([$project1, $project2]);

        $changes = $team->projectsWithPivotModel()->sync([$project2, $project3]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);

        $remaining = $team->fresh()->projectsWithPivotModel;
        $this->assertCount(2, $remaining);
        $names = $remaining->pluck('name')->sort()->values()->all();
        $this->assertEquals(['API', 'Mobile'], $names);
    }

    public function test_custom_pivot_model_toggle()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projectsWithPivotModel()->attach($project1);

        $changes = $team->projectsWithPivotModel()->toggle([$project1, $project2]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);

        $remaining = $team->fresh()->projectsWithPivotModel;
        $this->assertCount(1, $remaining);
        $this->assertEquals('API', $remaining->first()->name);
    }

    public function test_custom_pivot_model_loading()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('US', 1, 'Website');

        $this->attachPivot($team, $project);

        $loadedProjects = $team->projectsWithPivotModel;

        $this->assertCount(1, $loadedProjects);
        $this->assertInstanceOf(ProjectTeamPivot::class, $loadedProjects->first()->pivot);
    }

    public function test_custom_pivot_model_queueable_id()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('EU', 2, 'Website');

        $team->projectsWithPivotModel()->attach($project);

        $loadedProjects = $team->projectsWithPivotModel;
        $pivot = $loadedProjects->first()->pivot;

        $queueableId = $pivot->getQueueableId();

        $this->assertStringContainsString('team_region_code', $queueableId);
        $this->assertStringContainsString('US', $queueableId);
        $this->assertStringContainsString('project_region_code', $queueableId);
        $this->assertStringContainsString('EU', $queueableId);
    }

    public function test_custom_pivot_model_query_for_restoration()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('EU', 2, 'Website');

        $team->projectsWithPivotModel()->attach($project);

        $loadedProjects = $team->projectsWithPivotModel;
        $pivot = $loadedProjects->first()->pivot;

        $queueableId = $pivot->getQueueableId();
        $query = $pivot->newQueryForRestoration($queueableId);

        $restored = $query->first();
        $this->assertNotNull($restored);
    }

    public function test_custom_pivot_model_collection_restoration()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projectsWithPivotModel()->attach([$project1, $project2]);

        $loadedProjects = $team->projectsWithPivotModel;
        $ids = $loadedProjects->map(function ($p) {
            return $p->pivot->getQueueableId();
        })->all();

        $pivot = $loadedProjects->first()->pivot;
        $query = $pivot->newQueryForRestoration($ids);

        $restored = $query->get();
        $this->assertCount(2, $restored);
    }

    public function test_custom_pivot_model_delete()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project = $this->createProject('US', 1, 'Website');

        $team->projectsWithPivotModel()->attach($project);
        $this->assertEquals(1, Capsule::table('project_team')->count());

        $loadedProjects = $team->projectsWithPivotModel;
        $pivot = $loadedProjects->first()->pivot;

        $pivot->delete();

        $this->assertEquals(0, Capsule::table('project_team')->count());
    }

    public function test_invalid_usage_exception()
    {
        $this->expectException(InvalidUsageException::class);

        $team = $this->createTeam('US', 1, 'Alpha');

        $team->belongsToMany(
            \Awobaz\Compoships\Tests\Models\Space::class,
            'project_team',
            ['team_region_code', 'team_division_id'],
            ['project_region_code', 'project_division_id'],
            ['region_code', 'division_id'],
            ['region_code', 'division_id']
        )->getResults();
    }

    protected function createTeam(string $regionCode, int $divisionId, string $name): Team
    {
        $team = new Team();
        $team->region_code = $regionCode;
        $team->division_id = $divisionId;
        $team->name = $name;
        $team->save();

        return $team;
    }

    protected function createProject(string $regionCode, int $divisionId, string $name): Project
    {
        $project = new Project();
        $project->region_code = $regionCode;
        $project->division_id = $divisionId;
        $project->name = $name;
        $project->save();

        return $project;
    }

    protected function attachPivot(Team $team, Project $project): void
    {
        Capsule::table('project_team')->insert([
            'team_region_code'    => $team->region_code,
            'team_division_id'    => $team->division_id,
            'project_region_code' => $project->region_code,
            'project_division_id' => $project->division_id,
        ]);
    }
}
