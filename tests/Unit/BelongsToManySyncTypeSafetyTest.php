<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\Team;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Closure;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Regression tests for type-safe composite-key sync()/toggle() comparisons
 * and arity-checked tuple normalization on the composite whereIn paths.
 */
#[CoversClass(\Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany::class)]
#[CoversClass(\Awobaz\Compoships\Database\Query\Builder::class)]
class BelongsToManySyncTypeSafetyTest extends TestCase
{
    public function test_sync_same_key_with_string_typed_component_updates_in_place()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'old']);
        $originalPivotId = Capsule::table('project_team')->value('id');

        Capsule::connection()->enableQueryLog();
        $changes = $team->projectsWithMeta()->sync([json_encode(['US', '1']) => ['role' => 'new']]);

        $this->assertCount(0, $changes['attached']);
        $this->assertCount(0, $changes['detached']);
        $this->assertCount(1, $changes['updated']);
        $this->assertEquals($originalPivotId, Capsule::table('project_team')->value('id'));
        $this->assertSame('new', Capsule::table('project_team')->value('role'));
        $this->assertNoBindingMismatch();
    }

    public function test_sync_same_key_with_custom_pivot_updates_in_place_with_valid_sql()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projectsWithPivotModel()->attach([['US', 1]], ['role' => 'old']);
        $originalPivotId = Capsule::table('project_team')->value('id');

        Capsule::connection()->enableQueryLog();
        $changes = $team->projectsWithPivotModel()->sync([json_encode(['US', 1]) => ['role' => 'new']]);

        $this->assertCount(0, $changes['attached']);
        $this->assertCount(0, $changes['detached']);
        $this->assertCount(1, $changes['updated']);
        $this->assertEquals($originalPivotId, Capsule::table('project_team')->value('id'));
        $this->assertSame('new', Capsule::table('project_team')->value('role'));
        $this->assertNoBindingMismatch();
    }

    public function test_sync_same_key_with_string_typed_component_on_custom_pivot_updates_in_place()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projectsWithPivotModel()->attach([['US', 1]], ['role' => 'old']);
        $originalPivotId = Capsule::table('project_team')->value('id');

        Capsule::connection()->enableQueryLog();
        $changes = $team->projectsWithPivotModel()->sync([json_encode(['US', '1']) => ['role' => 'new']]);

        $this->assertCount(0, $changes['attached']);
        $this->assertCount(0, $changes['detached']);
        $this->assertCount(1, $changes['updated']);
        $this->assertEquals($originalPivotId, Capsule::table('project_team')->value('id'));
        $this->assertSame('new', Capsule::table('project_team')->value('role'));
        $this->assertNoBindingMismatch();
    }

    public function test_sync_matches_pre_encoded_json_keys_regardless_of_formatting()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'old']);

        $keyVariants = [
            '["US",1]'   => 'first',
            '["US", 1]'  => 'second',
            '["US","1"]' => 'third',
        ];

        foreach ($keyVariants as $key => $role) {
            $changes = $team->projectsWithMeta()->sync([$key => ['role' => $role]]);

            $this->assertCount(0, $changes['attached'], 'Key variant '.$key.' was not matched');
            $this->assertCount(0, $changes['detached'], 'Key variant '.$key.' caused a detach');
            $this->assertCount(1, $changes['updated'], 'Key variant '.$key.' did not update');
            $this->assertSame($role, Capsule::table('project_team')->value('role'));
            $this->assertSame(1, Capsule::table('project_team')->count());
        }
    }

    public function test_sync_updates_existing_and_attaches_new()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');
        $team->projectsWithMeta()->attach([['US', 1]], ['role' => 'old']);

        $changes = $team->projectsWithMeta()->sync([
            json_encode(['US', '1']) => ['role' => 'new'],
            ['EU', 2],
        ]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(0, $changes['detached']);
        $this->assertCount(1, $changes['updated']);
        $this->assertSame(2, Capsule::table('project_team')->count());
    }

    public function test_sync_empty_detaches_all_on_custom_pivot_relation()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');
        $team->projectsWithPivotModel()->attach([['US', 1], ['EU', 2]]);

        Capsule::connection()->enableQueryLog();
        $changes = $team->projectsWithPivotModel()->sync([]);

        $this->assertCount(2, $changes['detached']);
        $this->assertSame(0, Capsule::table('project_team')->count());
        $this->assertNoBindingMismatch();
    }

    public function test_toggle_with_string_typed_component_detaches_existing_row()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projects()->attach([['US', 1]]);

        $changes = $team->projects()->toggle([['US', '1']]);

        $this->assertCount(0, $changes['attached']);
        $this->assertCount(1, $changes['detached']);
        $this->assertSame(0, Capsule::table('project_team')->count());
    }

    public function test_changes_arrays_preserve_value_types()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');
        $team->projects()->attach([['US', 1]]);

        $changes = $team->projects()->sync([['EU', 2]]);

        $this->assertSame([['EU', 2]], $changes['attached']);
        $this->assertSame([['US', 1]], $changes['detached']);
    }

    public function test_detach_with_wrong_arity_tuple_throws_before_any_sql()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projects()->attach([['US', 1]]);

        Capsule::connection()->enableQueryLog();

        try {
            $team->projects()->detach([['US']]);
            $this->fail('Expected InvalidUsageException for wrong-arity tuple');
        } catch (InvalidUsageException $exception) {
            $this->assertStringContainsString('arity', $exception->getMessage());
        }

        $this->assertSame([], Capsule::connection()->getQueryLog());
        $this->assertSame(1, Capsule::table('project_team')->count());
    }

    public function test_detach_with_scalar_id_on_custom_pivot_relation_throws_before_any_sql()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projectsWithPivotModel()->attach([['US', 1]]);

        Capsule::connection()->enableQueryLog();

        try {
            $team->projectsWithPivotModel()->detach('US');
            $this->fail('Expected InvalidUsageException for scalar id on composite relation');
        } catch (InvalidUsageException $exception) {
            $this->assertStringContainsString('arity', $exception->getMessage());
        }

        $this->assertSame([], Capsule::connection()->getQueryLog());
        $this->assertSame(1, Capsule::table('project_team')->count());
    }

    public function test_detach_with_empty_array_is_a_noop_on_custom_pivot_relation()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projectsWithPivotModel()->attach([['US', 1]]);

        $result = $team->projectsWithPivotModel()->detach([]);

        $this->assertSame(0, $result);
        $this->assertSame(1, Capsule::table('project_team')->count());
    }

    public function test_composite_where_in_with_empty_values_matches_nothing()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $team->projects()->attach([['US', 1]]);

        $query = Team::query()->getQuery()->from('project_team')->whereIn(
            ['project_region_code', 'project_division_id'],
            []
        );

        $this->assertStringContainsString('0 = 1', $query->toSql());
        $this->assertCount(0, $query->get());
    }

    public function test_composite_where_in_with_wrong_arity_tuple_throws()
    {
        $this->expectException(InvalidUsageException::class);

        Team::query()->getQuery()->from('project_team')->whereIn(
            ['project_region_code', 'project_division_id'],
            [['US']]
        );
    }

    public function test_sync_update_targets_row_by_composite_key_on_pivot_table_without_surrogate_id()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');
        $team->projectsWithPivotModelNoId()->attach([['US', 1], ['EU', 2]], ['role' => 'old']);

        Capsule::connection()->enableQueryLog();
        $changes = $team->projectsWithPivotModelNoId()->sync([
            json_encode(['US', '1']) => ['role' => 'new'],
            ['EU', 2],
        ]);

        $this->assertCount(0, $changes['attached']);
        $this->assertCount(0, $changes['detached']);
        $this->assertCount(1, $changes['updated']);
        $this->assertSame('new', Capsule::table('project_team_no_id')->where('project_region_code', 'US')->value('role'));
        $this->assertSame('old', Capsule::table('project_team_no_id')->where('project_region_code', 'EU')->value('role'));
        $this->assertSame(2, Capsule::table('project_team_no_id')->count());
        $this->assertNoBindingMismatch();
    }

    public function test_detach_deletes_row_by_composite_key_on_pivot_table_without_surrogate_id()
    {
        $team = $this->createTeam('US', 1, 'Alpha');
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');
        $team->projectsWithPivotModelNoId()->attach([['US', 1], ['EU', 2]]);

        $result = $team->projectsWithPivotModelNoId()->detach([['US', 1]]);

        $this->assertSame(1, $result);
        $this->assertSame(1, Capsule::table('project_team_no_id')->count());
        $this->assertSame('EU', Capsule::table('project_team_no_id')->value('project_region_code'));
    }

    public function test_canonical_key_normalizes_scalar_types_and_preserves_null()
    {
        $relation = $this->createTeam('US', 1, 'Alpha')->projects();
        $serialize = Closure::bind(function (array $tuple) {
            return $this->serializeCompositeKey($tuple);
        }, $relation, BelongsToMany::class);

        $this->assertSame($serialize(['US', 1]), $serialize(['US', '1']));
        $this->assertNotSame($serialize(['US', null]), $serialize(['US', '']));
    }

    protected function createTeam(string $regionCode, int $divisionId, string $name): Team
    {
        return Team::create([
            'region_code' => $regionCode,
            'division_id' => $divisionId,
            'name'        => $name,
        ]);
    }

    protected function createProject(string $regionCode, int $divisionId, string $name): Project
    {
        return Project::create([
            'region_code' => $regionCode,
            'division_id' => $divisionId,
            'name'        => $name,
        ]);
    }

    protected function assertNoBindingMismatch(): void
    {
        foreach (Capsule::connection()->getQueryLog() as $query) {
            $this->assertSame(
                substr_count($query['query'], '?'),
                count($query['bindings']),
                'Placeholder/binding count mismatch in: '.$query['query']
            );
        }
    }
}
