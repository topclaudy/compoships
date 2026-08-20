<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BelongsToMany::class)]
class BelongsToManyTouchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_relation_touch_updates_only_the_attached_related_rows()
    {
        $project = Project::create(['region_code' => 'US', 'division_id' => 1, 'name' => 'p']);
        $related = Team::create(['region_code' => 'US', 'division_id' => 2, 'name' => 'related']);
        $decoy = Team::create(['region_code' => 'EU', 'division_id' => 7, 'name' => 'decoy with scalar id 2']);
        $this->assertSame(2, $decoy->id, 'the decoy must carry the scalar id that a coerced tuple would hit');
        $project->teams()->attach([['US', 2]]);

        Carbon::setTestNow('2021-01-01 00:00:00');
        $project->teams()->touch();

        $this->assertSame('2021-01-01 00:00:00', Capsule::table('teams')->where('id', $related->id)->value('updated_at'));
        $this->assertSame('2020-10-29 23:59:59', Capsule::table('teams')->where('id', $decoy->id)->value('updated_at'));
    }

    public function test_touches_on_the_parent_model_updates_the_attached_related_rows()
    {
        $project = Project::create(['region_code' => 'US', 'division_id' => 1, 'name' => 'p']);
        $related = Team::create(['region_code' => 'US', 'division_id' => 2, 'name' => 'related']);
        $project->teams()->attach([['US', 2]]);

        $touching = new class() extends Project {
            protected $table = 'projects';

            protected $touches = ['teams'];
        };
        $touchingProject = $touching->newQuery()->find($project->id);

        Carbon::setTestNow('2021-06-01 00:00:00');
        $touchingProject->name = 'renamed';
        $touchingProject->save();

        $this->assertSame('2021-06-01 00:00:00', Capsule::table('teams')->where('id', $related->id)->value('updated_at'));
    }
}
