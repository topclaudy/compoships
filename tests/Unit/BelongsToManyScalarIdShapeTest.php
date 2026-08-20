<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BelongsToMany::class)]
class BelongsToManyScalarIdShapeTest extends TestCase
{
    /** @var array<int, string> */
    private array $warnings = [];

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->warnings = [];
        set_error_handler(function (int $errno, string $errstr): bool {
            $this->warnings[] = $errstr;

            return true;
        }, E_WARNING | E_NOTICE);

        $this->team = Team::create(['region_code' => 'US', 'division_id' => 1, 'name' => 'Alpha']);
        Project::create(['region_code' => 'US', 'division_id' => 5, 'name' => 'Website']);
    }

    protected function tearDown(): void
    {
        restore_error_handler();

        parent::tearDown();
    }

    private function pivotRow(): object
    {
        $row = Capsule::table('project_team')->first();
        $this->assertNotNull($row, 'expected one pivot row');
        $this->assertSame(1, Capsule::table('project_team')->count());

        return $row;
    }

    public function test_sync_with_a_scalar_id_and_attributes_supplying_the_remaining_key_column()
    {
        $changes = $this->team->projectsByDivision()->sync([5 => ['project_region_code' => 'US', 'role' => 'lead']]);

        $row = $this->pivotRow();
        $this->assertSame(5, (int) $row->project_division_id);
        $this->assertSame('US', $row->project_region_code);
        $this->assertSame('lead', $row->role);
        $this->assertCount(1, $changes['attached']);
        $this->assertSame([], $this->warnings);
    }

    public function test_toggle_with_a_scalar_id_and_attributes()
    {
        $this->team->projectsByDivision()->toggle([5 => ['project_region_code' => 'US']]);

        $row = $this->pivotRow();
        $this->assertSame(5, (int) $row->project_division_id);
        $this->assertSame('US', $row->project_region_code);
        $this->assertSame([], $this->warnings);
    }

    public function test_attach_with_an_empty_per_row_array_uses_shared_attributes()
    {
        $this->team->projectsByDivision()->attach([5 => []], ['project_region_code' => 'US']);

        $row = $this->pivotRow();
        $this->assertSame(5, (int) $row->project_division_id);
        $this->assertSame('US', $row->project_region_code);
        $this->assertSame([], $this->warnings);
    }

    public function test_sync_resolves_a_backed_enum_scalar_key()
    {
        $enum = \Awobaz\Compoships\Tests\Enums\PivotRole::Lead;
        Project::create(['region_code' => 'US', 'division_id' => 6, 'name' => 'Enum']);
        $relation = $this->team->projectsWithMeta();

        $relation->sync([json_encode(['US', 6]) => ['role' => $enum]]);

        $this->assertSame('lead', Capsule::table('project_team')->value('role'));
        $this->assertSame([], $this->warnings);
    }

    public function test_toggle_and_sync_insert_new_rows_with_a_single_statement()
    {
        Project::create(['region_code' => 'EU', 'division_id' => 6, 'name' => 'Second']);

        Capsule::connection()->enableQueryLog();
        $this->team->projectsWithMeta()->toggle([['US', 5], ['EU', 6]]);
        $inserts = array_filter(Capsule::connection()->getQueryLog(), fn ($q) => str_starts_with($q['query'], 'insert'));
        $this->assertCount(1, $inserts);
        $this->assertSame(2, Capsule::table('project_team')->count());

        $this->team->projectsWithMeta()->detach();
        Capsule::connection()->flushQueryLog();
        $this->team->projectsWithMeta()->sync([['US', 5], ['EU', 6]]);
        $inserts = array_filter(Capsule::connection()->getQueryLog(), fn ($q) => str_starts_with($q['query'], 'insert'));
        $this->assertCount(1, $inserts);
        $this->assertSame(2, Capsule::table('project_team')->count());
    }
}
