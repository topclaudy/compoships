<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Factories\Relationship;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\OriginalPackage;
use Awobaz\Compoships\Tests\Models\TrackingTask;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Relationship::class)]
class FactoryRelationshipTest extends TestCase
{
    public function test_has_applies_the_relation_pending_attributes_on_a_scalar_relation()
    {
        if (!method_exists(\Illuminate\Database\Eloquent\Factories\Factory::class, 'prependState')) {
            $this->markTestSkipped('Factory::prependState() is not available on this Laravel version.');
        }

        Allocation::factory()->has(OriginalPackage::factory(), 'presetPackages')->create();

        $this->assertSame('preset', Capsule::table('original_packages')->value('name'));
    }

    public function test_factory_state_overrides_the_relation_pending_attributes()
    {
        if (!method_exists(\Illuminate\Database\Eloquent\Factories\Factory::class, 'prependState')) {
            $this->markTestSkipped('Factory::prependState() is not available on this Laravel version.');
        }

        Allocation::factory()->has(OriginalPackage::factory()->state(['name' => 'explicit']), 'presetPackages')->create();

        $this->assertSame('explicit', Capsule::table('original_packages')->value('name'));
    }

    public function test_has_sets_every_foreign_key_on_a_composite_relation()
    {
        $allocation = Allocation::factory()->has(TrackingTask::factory()->count(2), 'presetTrackingTasks')->create();

        $rows = Capsule::table('tracking_tasks')->get();

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame($allocation->booking_id, (int) $row->booking_id);
            $this->assertSame($allocation->vehicle_id, (int) $row->vehicle_id);
        }
    }
}
