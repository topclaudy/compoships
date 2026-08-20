<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\Pivot;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Pivot::class)]
class PivotQueueableIdTest extends TestCase
{
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->team = Team::create(['region_code' => 'US', 'division_id' => 1, 'name' => 't']);
    }

    private function attachedPivot(string $regionCode, int $divisionId): Pivot
    {
        Project::create(['region_code' => $regionCode, 'division_id' => $divisionId, 'name' => 'p']);
        $this->team->projectsWithPivotModelNoId()->attach([[$regionCode, $divisionId]]);

        return $this->team->projectsWithPivotModelNoId()
            ->where('projects.region_code', $regionCode)
            ->where('projects.division_id', $divisionId)
            ->first()->pivot;
    }

    public function test_pivot_value_containing_a_colon_round_trips()
    {
        $pivot = $this->attachedPivot('US:WEST', 5);

        $restored = $pivot->newQueryForRestoration($pivot->getQueueableId())->first();

        $this->assertNotNull($restored);
        $this->assertSame('US:WEST', $restored->project_region_code);
        $this->assertSame(5, (int) $restored->project_division_id);
    }

    public function test_queueable_id_is_json()
    {
        $pivot = $this->attachedPivot('US', 5);

        $decoded = json_decode($pivot->getQueueableId(), true);

        $this->assertSame(['team_region_code' => 'US', 'team_division_id' => 1, 'project_region_code' => 'US', 'project_division_id' => 5], array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $decoded));
    }

    public function test_legacy_colon_format_id_still_restores()
    {
        $this->attachedPivot('US', 5);
        $legacy = 'team_region_code:US:team_division_id:1:project_region_code:US:project_division_id:5';

        $restored = $this->team->projectsWithPivotModelNoId()->newPivot()->newQueryForRestoration($legacy)->first();

        $this->assertNotNull($restored);
        $this->assertSame('US', $restored->project_region_code);
    }

    public function test_collection_of_queueable_ids_restores_every_pivot()
    {
        $first = $this->attachedPivot('US', 5);
        $second = $this->attachedPivot('EU:N', 6);

        $restored = $first->newQueryForRestoration([$first->getQueueableId(), $second->getQueueableId()])->get();

        $this->assertCount(2, $restored);
    }
}
