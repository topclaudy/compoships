<?php

namespace Awobaz\Compoships\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration as BaseMigration;
use Illuminate\Database\Schema\Blueprint;

class Migration extends BaseMigration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')
                ->unsigned()
                ->nullable();
            $table->integer('booking_id')
                ->unsigned()
                ->nullable();
            $table->integer('vehicle_id')
                ->unsigned()
                ->nullable();
            $table->timestamps();
        });

        Capsule::schema()->create('spaces', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('booking_id')
                ->unsigned();
            $table->timestamps();
        });

        Capsule::schema()->create('tracking_tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('booking_id')
                ->unsigned()
                ->nullable();
            $table->integer('vehicle_id')
                ->unsigned()
                ->nullable();

            $table->foreign('booking_id')
                ->references('booking_id')
                ->on('allocations')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('vehicle_id')
                ->references('vehicle_id')
                ->on('allocations')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });

        Capsule::schema()->create('pickup_points', function (Blueprint $table) {
            $table->string('contract_number');
            $table->integer('pickup_index')
                ->unsigned();
            $table->timestamps();
        });

        Capsule::schema()->create('pickup_times', function (Blueprint $table) {
            $table->string('contract_number');
            $table->integer('pickup_index')
                ->unsigned();
            $table->string('days')
                ->unsigned();
            $table->time('pickup_time')
                ->unsigned();

            $table->foreign('pickup_index')
                ->references('pickup_index')
                ->on('pickup_point')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->timestamps();
        });

        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('booking_id')
                ->unsigned()
                ->nullable();
            $table->timestamps();
        });
    }
}
