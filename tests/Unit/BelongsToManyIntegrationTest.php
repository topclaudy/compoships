<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Tests\Models\Node;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Integration coverage for composite-key belongsToMany relations against the
 * surrounding Eloquent surface: pivot data access (withPivot, timestamps,
 * direct updateExistingPivot), pivot WHERE constraints, constrained eager
 * loading, existence/aggregate primitives, parent touch propagation, and
 * self-referencing relationships. These tests pin contracts that our overrides
 * already satisfy but no prior test exercised directly.
 *
 * Most tests use the new `Team::projectsWithMeta()` relation which adds
 * `->withPivot('role')->withTimestamps()` on top of the existing composite
 * `Team::projects()` definition.
 *
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany
 */
class BelongsToManyIntegrationTest extends TestCase
{
    // -----------------------------------------------------------------
    // 2. Pivot data interaction
    // -----------------------------------------------------------------

    public function test_with_pivot_role_column_is_readable_on_lazy_load()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'lead']);

        $loaded = $team->projectsWithMeta;

        $this->assertCount(1, $loaded);
        $this->assertEquals('lead', $loaded->first()->pivot->role);
    }

    public function test_with_pivot_role_column_is_readable_on_eager_load()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'reviewer']);

        $teams = Team::with('projectsWithMeta')->get();

        $this->assertCount(1, $teams);
        $this->assertCount(1, $teams->first()->projectsWithMeta);
        $this->assertEquals('reviewer', $teams->first()->projectsWithMeta->first()->pivot->role);
    }

    public function test_with_timestamps_populates_created_and_updated_at_on_attach()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'lead']);

        $row = (array) Capsule::table('project_team')->first();

        $this->assertNotNull($row['created_at']);
        $this->assertNotNull($row['updated_at']);
    }

    public function test_update_existing_pivot_with_composite_tuple_updates_row()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 12, 0, 0));
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'old']);

        $initialUpdatedAt = (array) Capsule::table('project_team')->first();

        Carbon::setTestNow(Carbon::create(2026, 1, 1, 12, 5, 0));
        $changed = $team->projectsWithMeta()->updateExistingPivot(['US', 1], ['role' => 'new']);

        Carbon::setTestNow();

        $row = (array) Capsule::table('project_team')->first();

        $this->assertEquals(1, $changed);
        $this->assertEquals('new', $row['role']);
        $this->assertNotEquals($initialUpdatedAt['updated_at'], $row['updated_at']);
    }

    public function test_update_existing_pivot_with_non_matching_tuple_is_noop()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');

        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'lead']);

        $changed = $team->projectsWithMeta()->updateExistingPivot(['XX', 99], ['role' => 'new']);

        $row = (array) Capsule::table('project_team')->first();

        $this->assertEquals(0, $changed);
        $this->assertEquals('lead', $row['role']);
    }

    // -----------------------------------------------------------------
    // 3. Query constraints (wherePivot / wherePivotIn)
    // -----------------------------------------------------------------

    public function test_where_pivot_filters_loaded_related_models()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'A']);
        $team->projectsWithMeta()->attach([['EU', 2]], ['role' => 'B']);

        $filtered = $team->projectsWithMeta()->wherePivot('role', 'A')->get();

        $this->assertCount(1, $filtered);
        $this->assertEquals('Website', $filtered->first()->name);
        $this->assertEquals('A', $filtered->first()->pivot->role);
    }

    public function test_where_pivot_in_filters_loaded_related_models()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');
        $this->createProject('AP', 3, 'Mobile');

        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'A']);
        $team->projectsWithMeta()->attach([['EU', 2]], ['role' => 'B']);
        $team->projectsWithMeta()->attach([['AP', 3]], ['role' => 'C']);

        $filtered = $team->projectsWithMeta()->wherePivotIn('role', ['A', 'C'])->get();

        $this->assertCount(2, $filtered);

        $names = $filtered->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Mobile', 'Website'], $names);
    }

    // -----------------------------------------------------------------
    // 4. Constrained eager loading
    // -----------------------------------------------------------------

    public function test_constrained_eager_loading_filters_per_parent_results()
    {
        $teamA = $this->createTeam('US', 1, 'Alpha');
        $teamB = $this->createTeam('EU', 2, 'Beta');

        $this->createProject('US', 1, 'Match');
        $this->createProject('EU', 2, 'NoMatch');
        $this->createProject('AP', 3, 'Match');
        $this->createProject('JP', 4, 'NoMatch');

        $teamA->projects()->attach([['US', 1]]);
        $teamA->projects()->attach([['EU', 2]]);
        $teamB->projects()->attach([['AP', 3]]);
        $teamB->projects()->attach([['JP', 4]]);

        $teams = Team::with(['projects' => fn ($q) => $q->where('name', 'Match')])
            ->orderBy('region_code')
            ->get();

        $this->assertCount(2, $teams);

        $this->assertCount(1, $teams[0]->projects);
        $this->assertEquals('Match', $teams[0]->projects->first()->name);

        $this->assertCount(1, $teams[1]->projects);
        $this->assertEquals('Match', $teams[1]->projects->first()->name);
    }

    // -----------------------------------------------------------------
    // 5. Existence and aggregate primitives
    // -----------------------------------------------------------------

    public function test_where_has_with_closure_constraint_narrows_parents()
    {
        $teamMatch = $this->createTeam('US', 1, 'WithMatch');
        $teamNoMatch = $this->createTeam('EU', 2, 'WithoutMatch');

        $this->createProject('US', 1, 'Match');
        $this->createProject('EU', 2, 'NoMatch');

        $teamMatch->projects()->attach([['US', 1]]);
        $teamNoMatch->projects()->attach([['EU', 2]]);

        $teams = Team::whereHas('projects', fn ($q) => $q->where('name', 'Match'))->get();

        $this->assertCount(1, $teams);
        $this->assertEquals('WithMatch', $teams->first()->name);
    }

    public function test_where_doesnt_have_returns_parents_with_no_attachments()
    {
        $teamWith = $this->createTeam('US', 1, 'WithProjects');
        $teamWithout = $this->createTeam('EU', 2, 'NoProjects');

        $this->createProject('US', 1, 'Website');

        $teamWith->projects()->attach([['US', 1]]);

        $teams = Team::whereDoesntHave('projects')->get();

        $this->assertCount(1, $teams);
        $this->assertEquals('NoProjects', $teams->first()->name);
    }

    public function test_has_with_comparison_filters_parents_by_attachment_count()
    {
        $team0 = $this->createTeam('US', 1, 'Zero');
        $team1 = $this->createTeam('EU', 2, 'One');
        $team2 = $this->createTeam('AP', 3, 'Two');
        $team3 = $this->createTeam('JP', 4, 'Three');

        $p1 = $this->createProject('US', 1, 'P1');
        $p2 = $this->createProject('EU', 2, 'P2');
        $p3 = $this->createProject('AP', 3, 'P3');

        $team1->projects()->attach([['US', 1]]);

        $team2->projects()->attach([['US', 1]]);
        $team2->projects()->attach([['EU', 2]]);

        $team3->projects()->attach([['US', 1]]);
        $team3->projects()->attach([['EU', 2]]);
        $team3->projects()->attach([['AP', 3]]);

        $teams = Team::has('projects', '>=', 2)->orderBy('region_code')->get();

        $this->assertCount(2, $teams);

        $names = $teams->pluck('name')->sort()->values()->all();
        $this->assertEquals(['Three', 'Two'], $names);
    }

    public function test_with_count_adds_attachment_count_column()
    {
        $team0 = $this->createTeam('US', 1, 'Zero');
        $team1 = $this->createTeam('EU', 2, 'One');
        $team2 = $this->createTeam('AP', 3, 'Two');

        $this->createProject('US', 1, 'P1');
        $this->createProject('EU', 2, 'P2');

        $team1->projects()->attach([['US', 1]]);

        $team2->projects()->attach([['US', 1]]);
        $team2->projects()->attach([['EU', 2]]);

        $teams = Team::withCount('projects')->orderBy('region_code')->get()->keyBy('name');

        $this->assertEquals(0, $teams['Zero']->projects_count);
        $this->assertEquals(1, $teams['One']->projects_count);
        $this->assertEquals(2, $teams['Two']->projects_count);
    }

    // -----------------------------------------------------------------
    // 6. Parent touch propagation
    //
    // Team declares `protected $touches = ['projectsWithMeta']`, so attach/
    // detach/sync on `projectsWithMeta` propagate to Team::updated_at via
    // touchIfTouching() when the touch flag is true and the mutation
    // changes pivot rows. The plain `projects` relation is touch-free.
    // -----------------------------------------------------------------

    public function test_attach_with_touch_enabled_updates_parent_updated_at()
    {
        $team = $this->createAndFreezeTeam('US', 1, 'Alpha', '2026-01-01 12:00:00');
        $this->createProject('US', 1, 'Website');

        $initialUpdatedAt = $team->fresh()->updated_at;

        Carbon::setTestNow(Carbon::parse('2026-01-01 12:05:00'));
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'lead']);
        Carbon::setTestNow();

        $this->assertNotEquals(
            (string) $initialUpdatedAt,
            (string) $team->fresh()->updated_at
        );
    }

    public function test_attach_with_touch_disabled_does_not_update_parent()
    {
        $team = $this->createAndFreezeTeam('US', 1, 'Alpha', '2026-01-01 12:00:00');
        $this->createProject('US', 1, 'Website');

        $initialUpdatedAt = $team->fresh()->updated_at;

        Carbon::setTestNow(Carbon::parse('2026-01-01 12:05:00'));
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'lead'], false);
        Carbon::setTestNow();

        $this->assertEquals(
            (string) $initialUpdatedAt,
            (string) $team->fresh()->updated_at
        );
    }

    public function test_detach_with_touch_enabled_updates_parent_updated_at()
    {
        $team = $this->createAndFreezeTeam('US', 1, 'Alpha', '2026-01-01 12:00:00');
        $this->createProject('US', 1, 'Website');

        Carbon::setTestNow(Carbon::parse('2026-01-01 12:05:00'));
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'lead']);

        $afterAttachUpdatedAt = $team->fresh()->updated_at;

        Carbon::setTestNow(Carbon::parse('2026-01-01 12:10:00'));
        $team->projectsWithMeta()->detach([['US', 1]]);
        Carbon::setTestNow();

        $this->assertNotEquals(
            (string) $afterAttachUpdatedAt,
            (string) $team->fresh()->updated_at
        );
    }

    public function test_sync_with_changes_updates_parent_updated_at()
    {
        $team = $this->createAndFreezeTeam('US', 1, 'Alpha', '2026-01-01 12:00:00');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        Carbon::setTestNow(Carbon::parse('2026-01-01 12:05:00'));
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'lead']);

        $afterAttachUpdatedAt = $team->fresh()->updated_at;

        Carbon::setTestNow(Carbon::parse('2026-01-01 12:10:00'));
        $team->projectsWithMeta()->sync([
            json_encode(['EU', 2]) => ['role' => 'reviewer'],
        ]);
        Carbon::setTestNow();

        $this->assertNotEquals(
            (string) $afterAttachUpdatedAt,
            (string) $team->fresh()->updated_at
        );
    }

    public function test_sync_with_no_changes_does_not_update_parent()
    {
        $team = $this->createAndFreezeTeam('US', 1, 'Alpha', '2026-01-01 12:00:00');
        $this->createProject('US', 1, 'Website');

        Carbon::setTestNow(Carbon::parse('2026-01-01 12:05:00'));
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'lead']);

        $afterAttachUpdatedAt = $team->fresh()->updated_at;

        // Sync with bare keys (no per-row attributes). Without per-row attrs,
        // updateExistingPivot is not called, so no rows are touched and the
        // sync produces zero changes. Passing per-row attrs (even with the
        // same value) would trigger an UPDATE that advances the pivot's
        // `updated_at`, which Laravel correctly counts as a change and
        // would propagate to the parent via touchIfTouching.
        Carbon::setTestNow(Carbon::parse('2026-01-01 12:10:00'));
        $team->projectsWithMeta()->sync([json_encode(['US', 1])]);
        Carbon::setTestNow();

        $this->assertEquals(
            (string) $afterAttachUpdatedAt,
            (string) $team->fresh()->updated_at
        );
    }

    // -----------------------------------------------------------------
    // 7. Self-referencing composite many-to-many (Node::links)
    // -----------------------------------------------------------------

    public function test_self_referencing_attach_and_lazy_load_round_trip()
    {
        $nodeA = $this->createNode('US', 1, 'NodeA');
        $nodeB = $this->createNode('EU', 2, 'NodeB');

        $nodeA->links()->attach($nodeB);

        $links = $nodeA->fresh()->links;

        $this->assertCount(1, $links);
        $this->assertEquals('EU', $links->first()->region_code);
        $this->assertEquals(2, (int) $links->first()->division_id);
        $this->assertEquals('NodeB', $links->first()->name);
    }

    public function test_self_referencing_eager_load_round_trip()
    {
        $nodeA = $this->createNode('US', 1, 'NodeA');
        $nodeB = $this->createNode('EU', 2, 'NodeB');
        $this->createNode('AP', 3, 'NodeC');

        $nodeA->links()->attach($nodeB);

        $loaded = Node::with('links')
            ->where('region_code', 'US')
            ->where('division_id', 1)
            ->get();

        $this->assertCount(1, $loaded);
        $this->assertCount(1, $loaded->first()->links);
        $this->assertEquals('NodeB', $loaded->first()->links->first()->name);
    }

    public function test_self_referencing_where_has_uses_aliased_self_join()
    {
        $nodeA = $this->createNode('US', 1, 'NodeA');
        $nodeB = $this->createNode('AP', 3, 'NodeB');
        $linkedEu = $this->createNode('EU', 99, 'LinkedEU');
        $linkedJp = $this->createNode('JP', 4, 'LinkedJP');

        $nodeA->links()->attach($linkedEu);
        $nodeB->links()->attach($linkedJp);

        $sql = Node::whereHas('links', fn ($q) => $q->where('region_code', 'EU'))->toSql();

        $this->assertStringNotContainsString('"nodes" inner join "node_links" on "nodes"."region_code"', $sql);

        $matches = Node::whereHas('links', fn ($q) => $q->where('region_code', 'EU'))
            ->orderBy('region_code')
            ->get();

        $this->assertCount(1, $matches);
        $this->assertEquals('NodeA', $matches->first()->name);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    protected function createTeam(string $regionCode, int $divisionId, string $name): Team
    {
        $team = new Team();
        $team->region_code = $regionCode;
        $team->division_id = $divisionId;
        $team->name = $name;
        $team->save();

        return $team;
    }

    /**
     * Create a team with `created_at` / `updated_at` set to a known frozen
     * timestamp. Required for touch tests where the assertion compares the
     * post-mutation `updated_at` against a known baseline.
     */
    protected function createAndFreezeTeam(string $regionCode, int $divisionId, string $name, string $frozenAt): Team
    {
        Carbon::setTestNow(Carbon::parse($frozenAt));
        $team = $this->createTeam($regionCode, $divisionId, $name);
        Carbon::setTestNow();

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

    protected function createNode(string $regionCode, int $divisionId, string $name): Node
    {
        $node = new Node();
        $node->region_code = $regionCode;
        $node->division_id = $divisionId;
        $node->name = $name;
        $node->save();

        return $node;
    }
}
