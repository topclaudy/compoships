<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\Awobaz\Compoships\Compoships::class)]
#[CoversClass(\Awobaz\Compoships\Database\Eloquent\Relations\HasMany::class)]
#[CoversClass(\Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany::class)]
#[CoversClass(\Awobaz\Compoships\Database\Eloquent\Concerns\HasRelationships::class)]
#[CoversClass(\Awobaz\Compoships\Database\Query\Builder::class)]
class BuilderTest extends TestCase
{
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
