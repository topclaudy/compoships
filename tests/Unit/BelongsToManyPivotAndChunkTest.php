<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany;
use Awobaz\Compoships\Database\Eloquent\Relations\Pivot;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\Models\User;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BelongsToMany::class)]
class BelongsToManyPivotAndChunkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_scalar_foreign_composite_related_relation_hydrates_the_package_pivot()
    {
        $user = User::create([]);
        Project::create(['region_code' => 'US', 'division_id' => 5, 'name' => 'p']);
        $user->projects()->attach([['US', 5]]);

        $pivot = $user->projects()->first()->pivot;

        $this->assertInstanceOf(Pivot::class, $pivot);
        $this->assertSame(1, $pivot->delete());
        $this->assertSame(0, Capsule::table('project_user')->count());
    }

    public function test_new_pivot_returns_the_package_pivot_on_every_composite_quadrant()
    {
        $user = User::create([]);
        $team = Team::create(['region_code' => 'US', 'division_id' => 1, 'name' => 't']);
        $project = Project::create(['region_code' => 'US', 'division_id' => 5, 'name' => 'p']);

        $this->assertInstanceOf(Pivot::class, $user->projects()->newPivot());
        $this->assertInstanceOf(Pivot::class, $project->users()->newPivot());
        $this->assertInstanceOf(Pivot::class, $team->projects()->newPivot());
    }

    public function test_sync_with_pivot_values_applies_shared_values_to_composite_tuples()
    {
        $team = Team::create(['region_code' => 'US', 'division_id' => 1, 'name' => 't']);
        Project::create(['region_code' => 'US', 'division_id' => 1, 'name' => 'a']);
        Project::create(['region_code' => 'EU', 'division_id' => 2, 'name' => 'b']);

        $changes = $team->projectsWithMeta()->syncWithPivotValues([['US', 1], ['EU', 2]], ['role' => 'member']);

        $this->assertCount(2, $changes['attached']);
        $this->assertSame(['member', 'member'], Capsule::table('project_team')->pluck('role')->all());
    }

    public function test_chunk_by_id_iterates_a_composite_relation()
    {
        $team = Team::create(['region_code' => 'US', 'division_id' => 1, 'name' => 't']);
        foreach ([1, 2, 3] as $division) {
            Project::create(['region_code' => 'US', 'division_id' => $division, 'name' => 'p'.$division]);
        }
        $team->projects()->attach([['US', 1], ['US', 2], ['US', 3]]);

        $seen = [];
        $chunks = 0;
        $team->projects()->chunkById(2, function ($projects) use (&$seen, &$chunks) {
            $chunks++;
            foreach ($projects as $project) {
                $seen[] = $project->division_id;
            }
        });

        $this->assertSame([1, 2, 3], $seen);
        $this->assertSame(2, $chunks);
        $this->assertSame([1, 2, 3], $team->projects()->lazyById(2)->pluck('division_id')->all());
    }

    public function test_eager_load_does_not_cross_match_parents_whose_key_components_contain_dashes()
    {
        $first = Team::create(['region_code' => 'x-y', 'division_id' => 'z', 'name' => 'first']);
        $second = Team::create(['region_code' => 'x', 'division_id' => 'y-z', 'name' => 'second']);
        Project::create(['region_code' => 'P', 'division_id' => 1, 'name' => 'p']);
        $second->projects()->attach([['P', 1]]);

        $teams = Team::with('projects')->orderBy('name')->get();

        $this->assertSame('first', $teams[0]->name);
        $this->assertCount(0, $teams[0]->projects);
        $this->assertCount(1, $teams[1]->projects);
    }
}
