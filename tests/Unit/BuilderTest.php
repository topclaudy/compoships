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
     * @covers \Awobaz\Compoships\Database\Query\Builder::whereIn
     */
    public function test_allocation_query_uses_compoships_builder_for_composite_where_in()
    {
        $query = Allocation::query()->whereIn(['booking_id', 'vehicle_id'], [[1, 10], [2, 20]]);

        $this->assertInstanceOf(
            \Awobaz\Compoships\Database\Query\Builder::class,
            $query->getQuery()
        );
        $this->assertStringContainsString(' IN ((?, ?), (?, ?))', $query->toSql());
        $this->assertSame([1, 10, 2, 20], $query->getBindings());
    }

    public function test_composite_where_in_with_more_than_1000_tuples_continues_to_work()
    {
        $tuples = array_map(
            fn ($value) => [$value, $value * 10],
            range(1, 1001)
        );

        $query = Allocation::query()->whereIn(['booking_id', 'vehicle_id'], $tuples);

        $this->assertInstanceOf(
            \Awobaz\Compoships\Database\Query\Builder::class,
            $query->getQuery()
        );
        $this->assertSame(1, substr_count(strtoupper($query->toSql()), ' IN ('));
        $this->assertCount(2002, $query->getBindings());
    }

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
