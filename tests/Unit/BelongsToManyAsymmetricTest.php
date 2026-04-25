<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Models\Project;
use Awobaz\Compoships\Tests\Models\User;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Coverage for asymmetric belongsToMany relations where one side is composite and
 * the other is scalar. The fork has historically guarded delegation on a single
 * side (relatedPivotKey OR foreignPivotKey), which mishandles the (scalar,
 * composite) and (composite, scalar) quadrants.
 *
 * Schema fixtures:
 *   User: scalar PK `id`
 *   Project: composite PK `(region_code, division_id)`
 *   project_user pivot: `user_id` + `(project_region_code, project_division_id)`
 *
 * Relations:
 *   User::projects()  -> scalar foreign + composite related
 *   Project::users()  -> composite foreign + scalar related
 *
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\BelongsToMany
 */
class BelongsToManyAsymmetricTest extends TestCase
{
    // -----------------------------------------------------------------
    // Direction A: scalar foreign + composite related (User::projects)
    // -----------------------------------------------------------------

    public function test_scalar_foreign_composite_related_attach_via_model()
    {
        $user = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $user->projects()->attach($project);

        $this->assertEquals(1, Capsule::table('project_user')->count());

        $row = (array) Capsule::table('project_user')->first();
        $this->assertEquals($user->id, $row['user_id']);
        $this->assertEquals('US', $row['project_region_code']);
        $this->assertEquals((string) 1, $row['project_division_id']);
    }

    public function test_scalar_foreign_composite_related_attach_via_composite_tuple()
    {
        $user = $this->createUser();
        $this->createProject('EU', 2, 'API');

        $user->projects()->attach([['EU', 2]]);

        $this->assertEquals(1, Capsule::table('project_user')->count());

        $row = (array) Capsule::table('project_user')->first();
        $this->assertEquals('EU', $row['project_region_code']);
        $this->assertEquals((string) 2, $row['project_division_id']);
    }

    public function test_scalar_foreign_composite_related_attach_via_json_key_attrs_map()
    {
        $user = $this->createUser();
        $this->createProject('EU', 2, 'API');

        $user->projects()->attach([
            json_encode(['EU', 2]) => ['role' => 'lead'],
        ]);

        $row = (array) Capsule::table('project_user')->first();
        $this->assertEquals('EU', $row['project_region_code']);
        $this->assertEquals((string) 2, $row['project_division_id']);
        $this->assertEquals('lead', $row['role']);
    }

    public function test_scalar_foreign_composite_related_attach_via_scalar_key_with_bulk_attr()
    {
        $user = $this->createUser();
        $this->createProject('EU', 2, 'API');

        $user->projects()->attach(
            ['EU' => ['role' => 'lead']],
            ['project_division_id' => 2]
        );

        $row = (array) Capsule::table('project_user')->first();
        $this->assertEquals('EU', $row['project_region_code']);
        $this->assertEquals((string) 2, $row['project_division_id']);
        $this->assertEquals('lead', $row['role']);
    }

    public function test_scalar_foreign_composite_related_attach_multiple_models()
    {
        $user = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $user->projects()->attach([$project1, $project2]);

        $this->assertEquals(2, Capsule::table('project_user')->count());
    }

    public function test_scalar_foreign_composite_related_attach_flat_scalar_list_with_bulk_attr()
    {
        // For asymmetric (scalar foreign + composite related) relations, a flat
        // list of scalar ids must be interpreted as "N rows, one per id" rather
        // than "one composite tuple". The remaining composite column comes from
        // the bulk attributes; baseAttachRecord's scalar-id branch fills only
        // relatedPivotKey[0] and lets the merge supply the rest.
        $user = $this->createUser();
        $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 1, 'Mobile');
        $this->createProject('AP', 1, 'API');

        $user->projects()->attach(['US', 'EU', 'AP'], ['project_division_id' => 1]);

        $this->assertEquals(3, Capsule::table('project_user')->count());

        $regions = Capsule::table('project_user')
            ->orderBy('project_region_code')
            ->pluck('project_region_code')
            ->all();
        $this->assertEquals(['AP', 'EU', 'US'], $regions);

        $divisions = Capsule::table('project_user')
            ->pluck('project_division_id')
            ->unique()
            ->values()
            ->all();
        $this->assertEquals(['1'], $divisions);
    }

    public function test_scalar_foreign_composite_related_relation_loading()
    {
        $user = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $this->createProject('EU', 2, 'API');

        $user->projects()->attach($project1);

        $loaded = $user->projects;

        $this->assertCount(1, $loaded);
        $this->assertEquals('Website', $loaded->first()->name);
    }

    public function test_scalar_foreign_composite_related_eager_loading()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $user1->projects()->attach($project1);
        $user2->projects()->attach($project2);

        $users = User::with('projects')->get();

        $this->assertCount(2, $users);
        $this->assertCount(1, $users[0]->projects);
        $this->assertCount(1, $users[1]->projects);
    }

    public function test_scalar_foreign_composite_related_sync()
    {
        $user = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');
        $project3 = $this->createProject('AP', 3, 'Mobile');

        $user->projects()->attach([$project1, $project2]);

        $changes = $user->projects()->sync([$project2, $project3]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);
        $this->assertEquals(2, Capsule::table('project_user')->count());
    }

    public function test_scalar_foreign_composite_related_detach_specific()
    {
        $user = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $user->projects()->attach([$project1, $project2]);
        $this->assertEquals(2, Capsule::table('project_user')->count());

        $user->projects()->detach($project1);

        $this->assertEquals(1, Capsule::table('project_user')->count());

        $remaining = (array) Capsule::table('project_user')->first();
        $this->assertEquals('EU', $remaining['project_region_code']);
    }

    public function test_scalar_foreign_composite_related_detach_all()
    {
        $user = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $user->projects()->attach([$project1, $project2]);
        $user->projects()->detach();

        $this->assertEquals(0, Capsule::table('project_user')->count());
    }

    public function test_scalar_foreign_composite_related_toggle()
    {
        $user = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $user->projects()->attach($project1);

        $changes = $user->projects()->toggle([$project1, $project2]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);
        $this->assertEquals(1, Capsule::table('project_user')->count());
    }

    // -----------------------------------------------------------------
    // Direction B: composite foreign + scalar related (Project::users)
    // -----------------------------------------------------------------

    public function test_composite_foreign_scalar_related_attach_via_model()
    {
        $user = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $project->users()->attach($user);

        $this->assertEquals(1, Capsule::table('project_user')->count());

        $row = (array) Capsule::table('project_user')->first();
        $this->assertEquals($user->id, $row['user_id']);
        $this->assertEquals('US', $row['project_region_code']);
        $this->assertEquals((string) 1, $row['project_division_id']);
    }

    public function test_composite_foreign_scalar_related_attach_via_scalar_id()
    {
        $user = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $project->users()->attach($user->id);

        $this->assertEquals(1, Capsule::table('project_user')->count());
    }

    public function test_composite_foreign_scalar_related_attach_via_id_attrs_map()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $project->users()->attach([
            $user1->id => ['role' => 'lead'],
            $user2->id => ['role' => 'reviewer'],
        ]);

        $this->assertEquals(2, Capsule::table('project_user')->count());

        $row1 = (array) Capsule::table('project_user')
            ->where('user_id', $user1->id)
            ->first();
        $this->assertEquals('lead', $row1['role']);

        $row2 = (array) Capsule::table('project_user')
            ->where('user_id', $user2->id)
            ->first();
        $this->assertEquals('reviewer', $row2['role']);
    }

    public function test_composite_foreign_scalar_related_relation_loading()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $project->users()->attach([$user1, $user2]);

        $loaded = $project->users;

        $this->assertCount(2, $loaded);
    }

    public function test_composite_foreign_scalar_related_eager_loading()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $project1->users()->attach($user1);
        $project2->users()->attach($user2);

        $projects = Project::with('users')->get();

        $this->assertCount(2, $projects);
        foreach ($projects as $project) {
            $this->assertCount(1, $project->users);
        }
    }

    public function test_composite_foreign_scalar_related_sync()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $user3 = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $project->users()->attach([$user1, $user2]);

        $changes = $project->users()->sync([$user2, $user3]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);
        $this->assertEquals(2, Capsule::table('project_user')->count());
    }

    public function test_composite_foreign_scalar_related_detach_specific()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $project->users()->attach([$user1, $user2]);

        $project->users()->detach($user1);

        $this->assertEquals(1, Capsule::table('project_user')->count());
    }

    public function test_composite_foreign_scalar_related_detach_all()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $project->users()->attach([$user1, $user2]);
        $project->users()->detach();

        $this->assertEquals(0, Capsule::table('project_user')->count());
    }

    public function test_composite_foreign_scalar_related_toggle()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $project = $this->createProject('US', 1, 'Website');

        $project->users()->attach($user1);

        $changes = $project->users()->toggle([$user1, $user2]);

        $this->assertCount(1, $changes['attached']);
        $this->assertCount(1, $changes['detached']);
        $this->assertEquals(1, Capsule::table('project_user')->count());
    }

    // -----------------------------------------------------------------
    // Regression: failure-mode contracts (clear errors, not silent NULLs)
    // -----------------------------------------------------------------

    public function test_attach_scalar_id_without_remaining_columns_throws_clear_exception()
    {
        $user = $this->createUser();
        $this->createProject('US', 1, 'Website');

        $this->expectException(InvalidUsageException::class);
        $this->expectExceptionMessageMatches('/Composite-key column.*missing/');

        $user->projects()->attach('US');
    }

    public function test_attach_scalar_id_with_remaining_columns_via_attrs_succeeds()
    {
        $user = $this->createUser();
        $this->createProject('US', 1, 'Website');

        $user->projects()->attach('US', ['project_division_id' => 1]);

        $this->assertEquals(1, Capsule::table('project_user')->count());

        $row = (array) Capsule::table('project_user')->first();
        $this->assertEquals('US', $row['project_region_code']);
        $this->assertEquals('1', (string) $row['project_division_id']);
    }

    public function test_detach_with_empty_array_is_no_op()
    {
        $user = $this->createUser();
        $project1 = $this->createProject('US', 1, 'Website');
        $project2 = $this->createProject('EU', 2, 'API');

        $user->projects()->attach([$project1, $project2]);
        $this->assertEquals(2, Capsule::table('project_user')->count());

        $deleted = $user->projects()->detach([]);

        $this->assertEquals(0, $deleted);
        $this->assertEquals(2, Capsule::table('project_user')->count());
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    protected function createUser(): User
    {
        $user = new User();
        $user->save();

        return $user;
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
}
