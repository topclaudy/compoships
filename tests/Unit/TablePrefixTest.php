<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\TrackingTask;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Awobaz\Compoships\Compoships::newBaseQueryBuilder
 */
class TablePrefixTest extends TestCase
{
    private const TABLE_PREFIX = 'test_prefix_';

    protected function setUp(): void
    {
        Model::reguard();
        Carbon::setTestNow('2020-10-29 23:59:59');

        $capsuleManager = new Capsule();
        $capsuleManager->addConnection([
            'driver'    => 'sqlite',
            'database'  => ':memory:',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => self::TABLE_PREFIX,
        ]);
        $capsuleManager->setAsGlobal();
        $capsuleManager->bootEloquent();

        Capsule::schema()->create('allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('booking_id')->unsigned()->nullable();
            $table->integer('vehicle_id')->unsigned()->nullable();
            $table->timestamps();
        });

        Capsule::schema()->create('tracking_tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('booking_id')->unsigned()->nullable();
            $table->integer('vehicle_id')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_table_prefix_is_applied_to_grammar()
    {
        $allocation = new Allocation();
        $grammar = $allocation->getConnection()->getQueryGrammar();

        $this->assertEquals(self::TABLE_PREFIX, $grammar->getTablePrefix());
    }

    public function test_queries_use_prefixed_table_names()
    {
        Capsule::table('allocations')->insert([
            'booking_id' => 1,
            'vehicle_id' => 10,
        ]);

        $allocation = Allocation::first();

        $this->assertNotNull($allocation);
        $this->assertEquals(1, $allocation->booking_id);
        $this->assertEquals(10, $allocation->vehicle_id);
    }

    public function test_compoship_relation_works_with_table_prefix()
    {
        Capsule::table('allocations')->insert([
            'booking_id' => 1,
            'vehicle_id' => 10,
        ]);

        Capsule::table('tracking_tasks')->insert([
            'booking_id' => 1,
            'vehicle_id' => 10,
        ]);

        Capsule::table('tracking_tasks')->insert([
            'booking_id' => 1,
            'vehicle_id' => 10,
        ]);

        Capsule::table('tracking_tasks')->insert([
            'booking_id' => 2,
            'vehicle_id' => 20,
        ]);

        $allocation = Allocation::with('trackingTasks')->first();

        $this->assertNotNull($allocation);
        $this->assertCount(2, $allocation->trackingTasks);
    }

    public function test_eager_loading_with_table_prefix()
    {
        Capsule::table('allocations')->insert([
            'booking_id' => 1,
            'vehicle_id' => 10,
        ]);

        Capsule::table('allocations')->insert([
            'booking_id' => 2,
            'vehicle_id' => 20,
        ]);

        Capsule::table('tracking_tasks')->insert([
            'booking_id' => 1,
            'vehicle_id' => 10,
        ]);

        Capsule::table('tracking_tasks')->insert([
            'booking_id' => 2,
            'vehicle_id' => 20,
        ]);

        $allocations = Allocation::with('trackingTasks')->get();

        $this->assertCount(2, $allocations);
        $this->assertCount(1, $allocations[0]->trackingTasks);
        $this->assertCount(1, $allocations[1]->trackingTasks);
    }

    public function test_belongs_to_relation_works_with_table_prefix()
    {
        Capsule::table('allocations')->insert([
            'booking_id' => 1,
            'vehicle_id' => 10,
        ]);

        Capsule::table('tracking_tasks')->insert([
            'booking_id' => 1,
            'vehicle_id' => 10,
        ]);

        $trackingTask = TrackingTask::with('allocation')->first();

        $this->assertNotNull($trackingTask);
        $this->assertNotNull($trackingTask->allocation);
        $this->assertEquals(1, $trackingTask->allocation->booking_id);
        $this->assertEquals(10, $trackingTask->allocation->vehicle_id);
    }
}
