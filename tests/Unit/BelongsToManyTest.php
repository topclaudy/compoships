<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Enums\PivotRole;
use Awobaz\Compoships\Tests\Models\Group;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\ProjectTeamPivot;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\Models\User;
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

    public function test_both_composite_attach_single_tuple_must_be_explicitly_wrapped()
    {
        // For composite-key relations, parseIds treats a flat list of scalars as
        // N independent ids (one row per element), not as a single composite
        // tuple. Callers passing a single tuple must wrap it explicitly:
        // `attach([['EU', 2]])` instead of `attach(['EU', 2])`.
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach([['EU', 2]]);

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
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

    public function test_attach_with_per_row_attributes_map()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach([
            json_encode(['US', 1]) => ['role' => 'lead'],
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
        ]);

        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rows = Capsule::table('project_team')->orderBy('project_region_code')->get()->all();

        $this->assertEquals('EU', $rows[0]->project_region_code);
        $this->assertEquals((string) 2, $rows[0]->project_division_id);
        $this->assertEquals('reviewer', $rows[0]->role);

        $this->assertEquals('US', $rows[1]->project_region_code);
        $this->assertEquals((string) 1, $rows[1]->project_division_id);
        $this->assertEquals('lead', $rows[1]->role);
    }

    public function test_attach_with_per_row_attributes_and_bulk_attributes()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projects()->attach(
            [json_encode(['US', 1]) => ['role' => 'lead']],
            ['role' => 'fallback']
        );

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('lead', $pivot['role']);
    }

    public function test_attach_supports_both_shapes_in_separate_calls()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach([['US', 1]]);
        $team->projects()->attach([json_encode(['EU', 2]) => ['role' => 'reviewer']]);

        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rowWithoutRole = (array) Capsule::table('project_team')
            ->where('project_region_code', 'US')
            ->first();
        $this->assertNull($rowWithoutRole['role']);

        $rowWithRole = (array) Capsule::table('project_team')
            ->where('project_region_code', 'EU')
            ->first();
        $this->assertEquals('reviewer', $rowWithRole['role']);
    }

    public function test_attach_filters_foreign_pivot_key_attributes()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projects()->attach([
            json_encode(['US', 1]) => [
                'team_region_code' => 'HACK',
                'team_division_id' => 99,
                'role'             => 'lead',
            ],
        ]);

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('US', $pivot['team_region_code']);
        $this->assertEquals((string) 1, $pivot['team_division_id']);
        $this->assertEquals('lead', $pivot['role']);
    }

    public function test_attach_with_bare_string_key_completes_composite_via_bulk_attr()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach(
            ['EU' => ['role' => 'lead']],
            ['project_division_id' => 2]
        );

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('EU', $pivot['project_region_code']);
        $this->assertEquals((string) 2, $pivot['project_division_id']);
        $this->assertEquals('lead', $pivot['role']);
        $this->assertEquals('US', $pivot['team_region_code']);
        $this->assertEquals((string) 1, $pivot['team_division_id']);
    }

    public function test_attach_with_bare_string_key_completes_composite_via_per_row_attr()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach([
            'EU' => ['project_division_id' => 2, 'role' => 'lead'],
        ]);

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('EU', $pivot['project_region_code']);
        $this->assertEquals((string) 2, $pivot['project_division_id']);
        $this->assertEquals('lead', $pivot['role']);
    }

    public function test_attach_rejects_wrong_arity_json_key()
    {
        $this->expectException(InvalidUsageException::class);

        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projects()->attach([json_encode(['US']) => ['role' => 'lead']]);
    }

    public function test_attach_rejects_malformed_json_array_key()
    {
        $this->expectException(InvalidUsageException::class);

        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        // Looks like an attempted JSON-encoded tuple (starts with `[`) but is invalid.
        $team->projects()->attach(['[broken' => ['role' => 'lead']]);
    }

    public function test_attach_with_support_collection_of_models()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $supportCollection = collect([$project1, $project2]);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $supportCollection);

        $team->projects()->attach($supportCollection);

        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rows = Capsule::table('project_team')->orderBy('project_region_code')->get();
        $this->assertEquals('EU', $rows[0]->project_region_code);
        $this->assertEquals('US', $rows[1]->project_region_code);
    }

    public function test_attach_with_support_collection_of_id_attrs_map()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('EU', 2, 'API');
        $this->createProject('AP', 3, 'Mobile');

        // Mirrors the user-reported pattern: collect()->mapWithKeys() produces a
        // Support\Collection where keys are scalar ids and values are per-row attrs.
        $programData = [
            ['program_id' => 'EU', 'role' => 'lead'],
            ['program_id' => 'AP', 'role' => 'reviewer'],
        ];

        $collection = collect($programData)->mapWithKeys(
            fn ($d) => [$d['program_id'] => ['role' => $d['role']]]
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $collection);

        $team->projects()->attach($collection, ['project_division_id' => 2]);

        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rowEU = (array) Capsule::table('project_team')
            ->where('project_region_code', 'EU')
            ->first();
        $this->assertEquals('lead', $rowEU['role']);

        $rowAP = (array) Capsule::table('project_team')
            ->where('project_region_code', 'AP')
            ->first();
        $this->assertEquals('reviewer', $rowAP['role']);
    }

    // -------------------------------------------------------------------------
    // Wrapper × shape matrix coverage for parseIds dispatch.
    //
    // The composite-key parseIds path dispatches on (input-wrapper-type, item-shape).
    // We've been bitten before by adding a wrapper case without re-checking every
    // item shape it can carry. The tests below explicitly exercise each cell of
    // the matrix so future modifications to the dispatch logic catch regressions.
    //
    // Wrapper types:  plain array, Support\Collection, Eloquent\Collection
    // Item shapes:    Models, composite tuples, [json_encode($tuple) => $attrs],
    //                 [$scalarId => $attrs]
    // -------------------------------------------------------------------------

    public function test_attach_with_support_collection_of_tuples()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $collection = collect([['US', 1], ['EU', 2]]);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $collection);

        $team->projects()->attach($collection);

        $this->assertEquals(2, Capsule::table('project_team')->count());
    }

    public function test_attach_with_support_collection_of_json_encoded_attrs_map()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $collection = collect([
            json_encode(['US', 1]) => ['role' => 'lead'],
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
        ]);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $collection);

        $team->projects()->attach($collection);

        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rowUS = (array) Capsule::table('project_team')
            ->where('project_region_code', 'US')
            ->first();
        $this->assertEquals('lead', $rowUS['role']);

        $rowEU = (array) Capsule::table('project_team')
            ->where('project_region_code', 'EU')
            ->first();
        $this->assertEquals('reviewer', $rowEU['role']);
    }

    public function test_attach_with_eloquent_collection_of_id_attrs_map()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('EU', 2, 'API');
        $this->createProject('AP', 3, 'Mobile');

        // Eloquent\Collection wraps an [id => attrs] map (extends Support\Collection,
        // so the same parseIds branch must handle it without assuming items are Models).
        $eloquentCollection = new \Illuminate\Database\Eloquent\Collection([
            'EU' => ['role' => 'lead'],
            'AP' => ['role' => 'reviewer'],
        ]);

        $team->projects()->attach($eloquentCollection, ['project_division_id' => 2]);

        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rowEU = (array) Capsule::table('project_team')
            ->where('project_region_code', 'EU')
            ->first();
        $this->assertEquals('lead', $rowEU['role']);
    }

    public function test_attach_with_eloquent_collection_of_json_encoded_attrs_map()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $eloquentCollection = new \Illuminate\Database\Eloquent\Collection([
            json_encode(['US', 1]) => ['role' => 'lead'],
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
        ]);

        $team->projects()->attach($eloquentCollection);

        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rowUS = (array) Capsule::table('project_team')
            ->where('project_region_code', 'US')
            ->first();
        $this->assertEquals('lead', $rowUS['role']);
    }

    public function test_sync_with_support_collection_of_id_attrs_map()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach($project1);

        $collection = collect([
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
        ]);

        $changes = $team->projects()->sync($collection->merge([
            'project_division_id_marker' => null,
        ])->forget('project_division_id_marker'), true);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);
        $this->assertEquals(1, Capsule::table('project_team')->count());

        $row = (array) Capsule::table('project_team')->first();
        $this->assertEquals('EU', $row['project_region_code']);
        $this->assertEquals('2', (string) $row['project_division_id']);
        $this->assertEquals('reviewer', $row['role']);
    }

    public function test_sync_with_support_collection_of_models()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $team->projects()->attach($project1);

        $changes = $team->projects()->sync(collect([$project2]));

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);
        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('EU', $pivot['project_region_code']);
    }

    public function test_attach_with_per_row_attributes_using_pivot_model()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projectsWithPivotModel()->attach([
            json_encode(['US', 1]) => ['role' => 'lead'],
        ]);

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('US', $pivot['project_region_code']);
        $this->assertEquals((string) 1, $pivot['project_division_id']);
        $this->assertEquals('lead', $pivot['role']);
    }

    public function test_sync_with_per_row_attributes_map()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');
        $this->createProject('AP', 3, 'Mobile');

        $team->projects()->attach([$project1, $project2]);
        $this->assertEquals(2, Capsule::table('project_team')->count());

        $changes = $team->projects()->sync([
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
            json_encode(['AP', 3]) => ['role' => 'lead'],
        ]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);

        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rowEU = (array) Capsule::table('project_team')
            ->where('project_region_code', 'EU')
            ->first();
        $this->assertEquals('reviewer', $rowEU['role']);

        $rowAP = (array) Capsule::table('project_team')
            ->where('project_region_code', 'AP')
            ->first();
        $this->assertEquals('lead', $rowAP['role']);
    }

    public function test_sync_without_detaching_with_per_row_attributes()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach($project1);

        $changes = $team->projects()->syncWithoutDetaching([
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
        ]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(0, $changes['detached']);
        $this->assertEquals(2, Capsule::table('project_team')->count());

        $rowEU = (array) Capsule::table('project_team')
            ->where('project_region_code', 'EU')
            ->first();
        $this->assertEquals('reviewer', $rowEU['role']);
    }

    public function test_toggle_with_per_row_attributes()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $team->projects()->attach($project1);

        $changes = $team->projects()->toggle([
            json_encode(['US', 1]) => [],
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
        ]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);

        $this->assertEquals(1, Capsule::table('project_team')->count());

        $row = (array) Capsule::table('project_team')->first();
        $this->assertEquals('EU', $row['project_region_code']);
        $this->assertEquals('reviewer', $row['role']);
    }

    public function test_single_key_attach_with_attrs_map_is_delegated_to_parent()
    {
        $user = new User();
        $user->save();

        $group1 = new Group();
        $group1->name = 'Admins';
        $group1->save();

        $group2 = new Group();
        $group2->name = 'Editors';
        $group2->save();

        $relation = $user->belongsToMany(Group::class, 'group_user', 'user_id', 'group_id')
            ->withPivot('role');

        $relation->attach([
            $group1->id => ['role' => 'lead'],
            $group2->id => ['role' => 'member'],
        ]);

        $this->assertEquals(2, Capsule::table('group_user')->count());

        $rowLead = (array) Capsule::table('group_user')
            ->where('group_id', $group1->id)
            ->first();
        $this->assertEquals('lead', $rowLead['role']);

        $rowMember = (array) Capsule::table('group_user')
            ->where('group_id', $group2->id)
            ->first();
        $this->assertEquals('member', $rowMember['role']);
    }

    public function test_attach_with_per_row_backed_enum_attribute_via_using_pivot_casts()
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('BackedEnum requires PHP 8.1+.');
        }

        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projectsWithEnumPivot()->attach([
            json_encode(['US', 1]) => ['role' => PivotRole::Lead],
        ]);

        $pivot = (array) Capsule::table('project_team')->first();
        $this->assertEquals('lead', $pivot['role']);
    }

    public function test_sync_with_per_row_attributes_using_pivot_model()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $project1 = $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $team->projectsWithPivotModel()->attach($project1);

        $changes = $team->projectsWithPivotModel()->sync([
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
        ]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);

        $rowEU = (array) Capsule::table('project_team')
            ->where('project_region_code', 'EU')
            ->first();
        $this->assertEquals('reviewer', $rowEU['role']);
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
