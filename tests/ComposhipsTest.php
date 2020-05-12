<?php

use Awobaz\Compoships\Database\Eloquent\Model;
use Awobaz\Compoships\Tests\Model\Allocation;
use Awobaz\Compoships\Tests\Model\PickupPoint;
use Awobaz\Compoships\Tests\Model\PickupTime;
use Awobaz\Compoships\Tests\Model\Space;
use Awobaz\Compoships\Tests\Model\TrackingTask;
use Awobaz\Compoships\Tests\Model\User;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

require_once __DIR__.'/TestCase.php';

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

        $allocation->trackingTasks()
            ->save(new TrackingTask());

        $this->assertNotNull($allocation->trackingTasks);
        $this->assertInstanceOf(Allocation::class, $allocation->trackingTasks->first()->allocation);

        Model::reguard();
    }

    /**
     * Test the save method on a relationship
     *
     * @return void
     */
    public function testSaveModelNotUsingCompoships()
    {
        Model::unguard();

        $allocation = new Allocation();
        $allocation->booking_id = 1;
        $allocation->vehicle_id = 1;
        $allocation->save();

        $allocation->space()
            ->save(new Space());

        $this->assertNotNull($allocation->space);

        Model::reguard();
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

        $allocation->trackingTasks()
            ->saveMany([
                new TrackingTask(),
                new TrackingTask(),
                new TrackingTask(),
            ]);

        $this->assertNotNull($allocation->trackingTasks);
        $this->assertEquals($allocation->trackingTasks->count(), 3);
        $this->assertInstanceOf(Allocation::class, $allocation->trackingTasks->first()->allocation);

        Model::reguard();
    }

    /**
     * Test the save method on a relationship with a null value
     *
     * @return void
     */
    public function testSaveWithANullValue()
    {
        Model::unguard();

        $allocation = new Allocation();
        $allocation->booking_id = 1;
        $allocation->vehicle_id = null;
        $allocation->save();

        $allocation->trackingTasks()
            ->save(new TrackingTask());

        $this->assertNotNull($allocation->trackingTasks);
        $this->assertTrue($allocation->trackingTasks->isNotEmpty());
        $this->assertInstanceOf(Allocation::class, $allocation->trackingTasks->first()->allocation);

        Model::reguard();
    }

    /**
     * Test a relationship with only null values is not supported
     *
     * @return void
     */
    public function testARelationshipWithOnlyNullValuesIsNotSupported()
    {
        Model::unguard();

        $allocation = new Allocation();
        $allocation->booking_id = null;
        $allocation->vehicle_id = null;
        $allocation->save();

        $allocation->trackingTasks()
            ->save(new TrackingTask());

        $this->assertNotNull($allocation->trackingTasks);
        $this->assertTrue($allocation->trackingTasks->isEmpty());
        $this->assertNull(TrackingTask::first()->allocation);

        Model::reguard();
    }

    /**
     * Test a relationship with a foreign key is empty on a new instance
     *
     * @return void
     */
    public function testARelationshipWithAForeignKeyIsEmptyOnANewInstance()
    {
        Model::unguard();

        $allocation = new Allocation();
        $allocation->user_id = null;
        $allocation->save();

        $user = new User();

        $this->assertNotNull($user->allocations);
        $this->assertTrue($user->allocations->isEmpty());

        Model::reguard();
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

        $allocation->trackingTasks()
            ->create([]);

        $this->assertNotNull($allocation->trackingTasks);
        $this->assertInstanceOf(Allocation::class, $allocation->trackingTasks->first()->allocation);

        Model::reguard();
    }

    /**
     * Test the make method on a relationship
     *
     * @return void
     */
    public function testMake()
    {
        Model::unguard();

        $allocation = new Allocation();

        if (! method_exists($allocation->trackingTasks(), 'make')) {
            return;
        }

        $allocation->booking_id = 1;
        $allocation->vehicle_id = 1;
        $allocation->save();

        $trackingTask = $allocation->trackingTasks()
            ->make([]);

        $this->assertNotNull($trackingTask);
        $this->assertInstanceOf(Allocation::class, $trackingTask->allocation);

        Model::reguard();
    }

    public function testHas()
    {
        $allocations = Allocation::has('trackingTasks')
            ->get()
            ->toArray();

        $this->assertInternalType('array', $allocations);
    }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testWhereHas()
    {
        $allocations = Allocation::wherehas('trackingTasks')
            ->get()
            ->toArray();

        $this->assertInternalType('array', $allocations);

        $allocations = Allocation::query()
            ->has('trackingTasks')
            ->get()
            ->toArray();

        $this->assertInternalType('array', $allocations);
    }

    public function testWhereHasCallback()
    {
        $allocations = Allocation::wherehas('trackingTasks', function ($query) {
            $query->where('vehicle_id', 1);
        })
            ->get()
            ->toArray();

        $this->assertInternalType('array', $allocations);
    }

    public function testMixedTypeCompositeKey()
    {
        Model::unguard();

        $pickupPoint = new PickupPoint();
        $pickupPoint->contract_number = 'AAA';
        $pickupPoint->pickup_index = 1;
        $pickupPoint->save();

        $pickupPoint->pickupTimes()
            ->create([
                'days'        => 'mon tue',
                'pickup_time' => '08:00:00',
            ]);

        $this->assertNotNull($pickupPoint->pickupTimes);

        Model::reguard();
    }

    public function testFactories()
    {
        $factory = app(Factory::class);

        $factory->define(Allocation::class, function (Faker $faker) {
            return [
                'booking_id' => rand(1, 100),
                'vehicle_id' => rand(1, 100),
            ];
        });

        $factory->define(TrackingTask::class, function (Faker $faker) {
            return [

            ];
        });

        factory(Allocation::class)
            ->create()
            ->each(function ($a) {
                $a->trackingTasks()
                    ->save(factory(TrackingTask::class)->make());
            });

        $allocation = Allocation::firstOrFail();

        $this->assertNotNull($allocation->trackingTasks);
    }

    public function testHasForSelfRelation()
    {
        $trackingTask = TrackingTask::has('subTasks')
            ->get()
            ->toArray();

        $this->assertInternalType('array', $trackingTask);
    }

    public function testHasWithBelongsToRelation()
    {
        $pickup_times = PickupTime::has('pickupPoint')
            ->get()
            ->toArray();

        $this->assertInternalType('array', $pickup_times);
    }
}