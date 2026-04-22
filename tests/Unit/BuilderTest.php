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
}
