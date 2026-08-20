<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HasOneOrMany::class)]
class HasOneOrManyConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_of_many_constrains_the_aggregate_subquery_and_the_outer_query_once()
    {
        $allocation = Allocation::create(['booking_id' => 1, 'vehicle_id' => 2]);

        $base = $allocation->latestTrackingTask()->toBase();
        $sql = $base->toSql();

        [$subquery, $outer] = explode(') as "latestOfMany"', $sql, 2);

        $this->assertStringContainsString('"tracking_tasks"."booking_id" = ? and "tracking_tasks"."vehicle_id" = ?', $subquery);
        $this->assertSame(1, substr_count($outer, '"tracking_tasks"."booking_id" = ?'));
        $this->assertSame(1, substr_count($outer, '"tracking_tasks"."vehicle_id" = ?'));
        $this->assertSame([1, 2, 1, 2], $base->getBindings());
    }

    public function test_of_many_still_resolves_the_latest_related_row()
    {
        $allocation = Allocation::create(['booking_id' => 1, 'vehicle_id' => 2]);
        $other = Allocation::create(['booking_id' => 1, 'vehicle_id' => 3]);
        $allocation->trackingTasks()->create([]);
        $latest = $allocation->trackingTasks()->create([]);
        $other->trackingTasks()->create([]);

        $this->assertSame($latest->id, $allocation->latestTrackingTask->id);
    }

    public function test_mixed_null_and_empty_string_parent_key_is_not_treated_as_all_null()
    {
        $allocation = new Allocation(['booking_id' => null, 'vehicle_id' => '']);

        $sql = $allocation->trackingTasks()->toSql();

        $this->assertStringContainsString('"tracking_tasks"."booking_id" is null', $sql);
        $this->assertStringContainsString('"tracking_tasks"."vehicle_id" = ?', $sql);
        $this->assertStringNotContainsString('is not null', $sql);
    }
}
