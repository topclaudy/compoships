<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\HasMany;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\OriginalPackage;
use Awobaz\Compoships\Tests\Models\ThreeColUser;
use Awobaz\Compoships\Tests\Models\ThreeColUserProfile;
use Awobaz\Compoships\Tests\Models\TrackingTask;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Eager loading an explicit subset of columns (`with('relation:columns')`) must keep
 * the composite key columns of the relationship, otherwise the relationship cannot be
 * matched back to its parents and resolves to an empty result.
 *
 * @see https://github.com/topclaudy/compoships/issues/98
 *
 * @covers \Awobaz\Compoships\Concerns\AddsMissingKeyColumnsToEagerSelect
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany::getEager
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasOne::getEager
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\HasMany::getEager
 * @covers \Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo::getEager
 */
class EagerLoadColumnSubsetTest extends TestCase
{
    public function test_has_many_eager_load_subset_keeps_composite_keys()
    {
        $allocation = $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);
        $this->createTrackingTask(1, 1);
        $this->createTrackingTask(2, 2);

        $loaded = Allocation::with('trackingTasks:id')->first();

        $this->assertCount(2, $loaded->trackingTasks);

        foreach ($loaded->trackingTasks as $trackingTask) {
            $this->assertEquals($allocation->booking_id, $trackingTask->booking_id);
            $this->assertEquals($allocation->vehicle_id, $trackingTask->vehicle_id);
        }
    }

    public function test_has_many_eager_load_subset_only_returns_requested_columns()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);

        $trackingTask = Allocation::with('trackingTasks:id')->first()->trackingTasks->first();

        $this->assertEquals(
            ['id', 'booking_id', 'vehicle_id'],
            array_keys($trackingTask->getAttributes())
        );
    }

    public function test_has_many_eager_load_subset_via_closure_with_raw_expression()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);
        $this->createTrackingTask(1, 1);

        $loaded = Allocation::with([
            'trackingTasks' => function (HasMany $query) {
                $query->select('id')->selectRaw('1 as one');
            },
        ])->first();

        $this->assertCount(2, $loaded->trackingTasks);
        $this->assertEquals(1, $loaded->trackingTasks->first()->one);
    }

    public function test_has_many_eager_load_subset_with_three_composite_keys()
    {
        $this->createThreeColUser('u1', 't1', 'r1');
        $this->createThreeColUser('u1', 't1', 'r2');
        $this->createThreeColUserProfile('u1', 't1', 'r1', 'alpha');
        $this->createThreeColUserProfile('u1', 't1', 'r1', 'beta');
        $this->createThreeColUserProfile('u1', 't1', 'r2', 'gamma');

        $loaded = ThreeColUser::with('profiles:id,label')
            ->where('id', 'u1')
            ->where('tenant_id', 't1')
            ->where('region_id', 'r1')
            ->first();

        $this->assertCount(2, $loaded->profiles);
        $this->assertEquals(['alpha', 'beta'], $loaded->profiles->pluck('label')->sort()->values()->all());
    }

    public function test_belongs_to_eager_load_subset_keeps_composite_keys()
    {
        $allocation = $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);

        $trackingTask = TrackingTask::with('allocation:id,booking_id')->first();

        $this->assertNotNull($trackingTask->allocation);
        $this->assertEquals($allocation->id, $trackingTask->allocation->id);
        $this->assertEquals($allocation->booking_id, $trackingTask->allocation->booking_id);
        $this->assertEquals($allocation->vehicle_id, $trackingTask->allocation->vehicle_id);
    }

    public function test_belongs_to_eager_load_subset_with_three_composite_keys()
    {
        $this->createThreeColUser('u1', 't1', 'r1');
        $this->createThreeColUserProfile('u1', 't1', 'r1', 'alpha');

        $profile = ThreeColUserProfile::with('user:id')->first();

        $this->assertNotNull($profile->user);
        $this->assertEquals('u1', $profile->user->id);
        $this->assertEquals('t1', $profile->user->tenant_id);
        $this->assertEquals('r1', $profile->user->region_id);
    }

    public function test_of_many_eager_load_subset_keeps_composite_keys()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);
        $latest = $this->createTrackingTask(1, 1);

        $loaded = Allocation::with(['latestTrackingTask:id', 'smallerTrackingTask:id'])->first();

        $this->assertNotNull($loaded->latestTrackingTask);
        $this->assertEquals($latest->id, $loaded->latestTrackingTask->id);
        $this->assertEquals(1, $loaded->smallerTrackingTask->id);
    }

    public function test_eager_load_subset_restores_keys_in_query()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);

        $queries = $this->captureQueries(function () {
            Allocation::with('trackingTasks:id')->first();
        });

        $select = $this->eagerSelectList($queries, 'tracking_tasks');

        $this->assertNotNull($select, 'no eager loading query was captured');
        $this->assertEquals(1, substr_count($select, 'booking_id'));
        $this->assertEquals(1, substr_count($select, 'vehicle_id'));
    }

    public function test_eager_load_subset_does_not_duplicate_selected_keys()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);

        $queries = $this->captureQueries(function () {
            Allocation::with('trackingTasks:id,booking_id,vehicle_id')->first();
        });

        $select = $this->eagerSelectList($queries, 'tracking_tasks');

        $this->assertNotNull($select, 'no eager loading query was captured');
        $this->assertEquals(1, substr_count($select, 'booking_id'));
        $this->assertEquals(1, substr_count($select, 'vehicle_id'));
    }

    public function test_eager_load_without_column_subset_is_unchanged()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);
        $this->createTrackingTask(1, 1);

        $loaded = Allocation::with('trackingTasks')->first();

        $this->assertCount(2, $loaded->trackingTasks);
        $this->assertArrayHasKey('deleted_at', $loaded->trackingTasks->first()->getAttributes());
    }

    public function test_eager_load_with_wildcard_column_subset_is_unchanged()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);

        $loaded = Allocation::with('trackingTasks:*')->first();

        $this->assertCount(1, $loaded->trackingTasks);
        $this->assertArrayHasKey('deleted_at', $loaded->trackingTasks->first()->getAttributes());
    }

    public function test_eager_load_subset_with_aggregates_is_unchanged()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);
        $this->createTrackingTask(1, 1);

        $loaded = Allocation::with('trackingTasks:id')->withCount('trackingTasks')->withSum('trackingTasks', 'id')->first();

        $this->assertCount(2, $loaded->trackingTasks);
        $this->assertEquals(2, $loaded->tracking_tasks_count);
        $this->assertEquals(3, $loaded->tracking_tasks_sum_id);
    }

    public function test_nested_eager_load_subset_keeps_composite_keys()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);
        $this->createTrackingTask(1, 1);

        $loaded = Allocation::with(['trackingTasks:id', 'trackingTasks.subTasks:id'])->first();

        $this->assertCount(2, $loaded->trackingTasks);
        $this->assertCount(2, $loaded->trackingTasks->first()->subTasks);
    }

    public function test_lazy_eager_load_subset_keeps_composite_keys()
    {
        $this->createAllocation(1, 1);
        $this->createTrackingTask(1, 1);
        $this->createTrackingTask(1, 1);

        $allocation = Allocation::first();
        $allocation->load('trackingTasks:id');

        $this->assertCount(2, $allocation->trackingTasks);
    }

    public function test_scalar_relationships_are_not_modified()
    {
        $allocation = $this->createAllocation(1, 1);

        $package = new OriginalPackage();
        $package->name = 'package';
        $allocation->originalPackages()->save($package);

        // Laravel's documented behavior for scalar relationships is kept: the foreign
        // key is not part of the requested subset, so nothing can be matched back.
        $loaded = Allocation::with('originalPackages:id,name')->first();

        $this->assertCount(0, $loaded->originalPackages);
    }

    /**
     * @param int $bookingId
     * @param int $vehicleId
     *
     * @return Allocation
     */
    private function createAllocation($bookingId, $vehicleId)
    {
        $allocation = new Allocation();
        $allocation->booking_id = $bookingId;
        $allocation->vehicle_id = $vehicleId;
        $allocation->save();

        return $allocation;
    }

    /**
     * @param int $bookingId
     * @param int $vehicleId
     *
     * @return TrackingTask
     */
    private function createTrackingTask($bookingId, $vehicleId)
    {
        $trackingTask = new TrackingTask();
        $trackingTask->booking_id = $bookingId;
        $trackingTask->vehicle_id = $vehicleId;
        $trackingTask->save();

        return $trackingTask;
    }

    /**
     * @param string $id
     * @param string $tenantId
     * @param string $regionId
     *
     * @return ThreeColUser
     */
    private function createThreeColUser($id, $tenantId, $regionId)
    {
        return ThreeColUser::create([
            'id'        => $id,
            'tenant_id' => $tenantId,
            'region_id' => $regionId,
            'name'      => $id.'-'.$regionId,
        ]);
    }

    /**
     * @param string $userId
     * @param string $tenantId
     * @param string $regionId
     * @param string $label
     *
     * @return ThreeColUserProfile
     */
    private function createThreeColUserProfile($userId, $tenantId, $regionId, $label)
    {
        return ThreeColUserProfile::create([
            'user_id'   => $userId,
            'tenant_id' => $tenantId,
            'region_id' => $regionId,
            'label'     => $label,
        ]);
    }

    /**
     * @param callable $callback
     *
     * @return array<int, string>
     */
    private function captureQueries(callable $callback)
    {
        $connection = Capsule::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $callback();

        $queries = array_map(
            fn (array $entry) => $entry['query'],
            $connection->getQueryLog()
        );

        $connection->disableQueryLog();

        return $queries;
    }

    /**
     * Get the select clause of the first select query running against the given table.
     *
     * @param array<int, string> $queries
     * @param string             $table
     *
     * @return string|null
     */
    private function eagerSelectList(array $queries, $table)
    {
        foreach ($queries as $query) {
            $fromPosition = stripos($query, ' from ');

            if ($fromPosition === false || stripos(ltrim($query), 'select') !== 0) {
                continue;
            }

            if (!str_contains(substr($query, $fromPosition), $table)) {
                continue;
            }

            return substr($query, 0, $fromPosition);
        }

        return null;
    }
}
