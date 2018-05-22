<?php

use Illuminate\Database\Migrations\Migration as BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Migration extends BaseMigration
{
    /**
     * Run the migrations.
     *
     * @return  void
     */
    public function up()
    {
        Schema::create('allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('booking_id')->unsigned();
            $table->integer('vehicle_id')->unsigned();
            $table->timestamps();
        });

        Schema::create('tracking_tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('booking_id')->unsigned();
            $table->integer('vehicle_id')->unsigned();

            $table->foreign('booking_id')->references('booking_id')->on('allocations')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('vehicle_id')->references('vehicle_id')->on('allocations')->onUpdate('cascade')->onDelete('cascade');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return  void
     */
    public function down()
    {
        Schema::drop('tracking_tasks');
        Schema::drop('allocations');
    }
}
