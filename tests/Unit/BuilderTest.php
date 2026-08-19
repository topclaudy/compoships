<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * @covers \Awobaz\Compoships\Compoships::getAttribute
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasMany::getResults
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany::getForeignKeyName
 */
class BuilderTest extends TestCase
{
    /**
     * @covers \Awobaz\Compoships\Compoships::newBaseQueryBuilder
     * @covers \Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships::hasMany
     * @covers \Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships::newHasMany
     * @covers \Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships::sanitizeKey
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany::addConstraints
     * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany::getQualifiedParentKeyName
     * @covers \Awobaz\Compoships\Database\Query\Builder::whereColumn
     */
    public function test_Illuminate_hasOneOrMany__Builder_whereColumn_on_relation_column()
    {
        $allocationId1 = Capsule::table('allocations')->insertGetId([
            'booking_id' => 1,
            'vehicle_id' => 1,
        ]);
        $allocationId2 = Capsule::table('allocations')->insertGetId([
            'booking_id' => 2,
            'vehicle_id' => 2,
        ]);
        $package1 = Capsule::table('original_packages')->insertGetId([
            'name'          => 'name 1',
            'allocation_id' => 1,
        ]);
        $package2 = Capsule::table('original_packages')->insertGetId([
            'name'          => 'name 2',
            'allocation_id' => 1,
        ]);

        /** @var Allocation[] $allocations */
        $allocations = Allocation::query()->whereHas('originalPackages', function ($query) {
            $query->where('id', 123);
        })->get();
        $this->assertCount(0, $allocations);

        /** @var Allocation[] $allocations */
        $allocations = Allocation::query()->whereHas('originalPackages', function ($query) {
            $query->where('id', 2);
        })->get();
        $this->assertCount(1, $allocations);
        $this->assertCount(2, $allocations[0]->originalPackages);
    }

    public function test_wherein_uses_fallback_when_null_values()
    {
        $allocationId1 = Capsule::table('allocations')->insertGetId([
            'user_id'    => 1,
            'booking_id' => 1,
        ]);
        $allocationId2 = Capsule::table('allocations')->insertGetId([
            'user_id'    => 2,
            'booking_id' => null,
        ]);
        $allocation1 = Allocation::find($allocationId1);
        $allocation2 = Allocation::find($allocationId2);

        $query1 = Allocation::query()->getRelation('user');
        $query1->addEagerConstraints([$allocation1]);
        $sql1 = $query1->toRawSql();

        $query2 = Allocation::query()->getRelation('user');
        $query2->addEagerConstraints([$allocation2]);
        $sql2 = $query2->toRawSql();

        $this->assertEquals('select * from "users" where ("users"."id", "users"."booking_id") IN ((1, 1))', $sql1);
        $this->assertEquals('select * from "users" where (("users"."id" = 2 and "users"."booking_id" is null))', $sql2);
    }
}
