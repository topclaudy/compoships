<?php

use Awobaz\Compoships\Database\Eloquent\Model;
use Awobaz\Compoships\Tests\Model\Allocation;
use Awobaz\Compoships\Tests\Model\TrackingTask;

require_once __DIR__. '/TestCase.php';

class ComposhipsTest extends TestCase
{
    /**
     * Test the save method on a relationship
     *
     * @return void
     */
    public function testSave()
    {
        Model::unguard();

        $allocation = new Allocation();
        $allocation->booking_id = 1;
        $allocation->vehicle_id = 1;
        $allocation->save();

        $allocation->trackingTasks()->save(new TrackingTask());

        $this->assertNotNull($allocation->trackingTasks);

        Model::unguard();
    }

    /**
     * Test the save method on a relationship
     *
     * @return void
     */
    public function testSaveMany()
    {
        Model::unguard();

        $allocation = new Allocation();
        $allocation->booking_id = 1;
        $allocation->vehicle_id = 1;
        $allocation->save();

        $allocation->trackingTasks()->saveMany([
            new TrackingTask(),
            new TrackingTask(),
            new TrackingTask()
        ]);

        $this->assertNotNull($allocation->trackingTasks);
        $this->assertEquals($allocation->trackingTasks->count(), 3);

        Model::unguard();
    }

    /**
     * Test the create method on a relationship
     *
     * @return void
     */
    public function testCreate()
    {
        Model::unguard();

        $allocation = new Allocation();
        $allocation->booking_id = 1;
        $allocation->vehicle_id = 1;
        $allocation->save();

        $allocation->trackingTasks()->create([]);

        $this->assertNotNull($allocation->trackingTasks);

        Model::unguard();
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testWhereHas()
    {
        $allocations = Allocation::wherehas('trackingTasks')->get()->toArray();

        $this->assertInternalType('array', $allocations);

        $allocations = Allocation::query()->has('trackingTasks')->get()->toArray();

        $this->assertInternalType('array', $allocations);
    }

    public function testWhereHasCallback()
    {
        $allocations = Allocation::wherehas('trackingTasks', function ($query)  {
            $query->where('vehicle_id',  1);
        })->get()->toArray();

        $this->assertInternalType('array', $allocations);
    }
}