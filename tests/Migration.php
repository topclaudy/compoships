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

        // contains original single PK relations
        Capsule::schema()->create('original_packages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('pcid')->nullable();
            $table->string('name')->nullable();
            $table->integer('allocation_id');

            $table->foreign('allocation_id')
                ->references('id')
                ->on('allocations')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('pcid')
                ->references('pcid')
                ->on('product_codes')
                ->onUpdate('cascade')
                ->onDelete('cascade');
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

        Capsule::schema()->create('product_codes', function (Blueprint $table) {
            $table->uuid('pcid')->unique();
            $table->string('code');
        });

        Capsule::schema()->create('user_profiles', function (Blueprint $table) {
            $table->integer('user_id')
                ->unsigned();
            $table->string('user_profile_type');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        Capsule::schema()->create('teams', function (Blueprint $table) {
            $table->increments('id');
            $table->string('region_code')->nullable();
            $table->integer('division_id')->unsigned()->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Capsule::schema()->create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->string('region_code');
            $table->integer('division_id')->unsigned();
            $table->string('name');
            $table->timestamps();
        });

        Capsule::schema()->create('project_team', function (Blueprint $table) {
            $table->increments('id');
            $table->string('team_region_code');
            $table->integer('team_division_id')->unsigned();
            $table->string('project_region_code');
            $table->integer('project_division_id')->unsigned();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        // Same shape as project_team but without a surrogate id column, so
        // pivot update/delete queries must locate rows by the composite keys
        // alone (the conventional Laravel pivot table layout).
        Capsule::schema()->create('project_team_no_id', function (Blueprint $table) {
            $table->string('team_region_code');
            $table->integer('team_division_id')->unsigned();
            $table->string('project_region_code');
            $table->integer('project_division_id')->unsigned();
            $table->string('role')->nullable();
        });

        // Pivot table for asymmetric belongsToMany tests:
        //   User (scalar PK 'id')  <->  Project (composite key 'region_code, division_id')
        // Used by both User::projects() and Project::users() to verify both
        // (scalar-foreign, composite-related) and (composite-foreign, scalar-related) paths.
        Capsule::schema()->create('project_user', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('project_region_code');
            $table->integer('project_division_id')->unsigned();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Capsule::schema()->create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        Capsule::schema()->create('group_user', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('group_id')->unsigned();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Capsule::schema()->create('user_profile_texts', function (Blueprint $table) {
            $table->integer('user_id')
                ->unsigned();
            $table->string('user_profile_type');
            $table->string('user_profile_text');
            $table->timestamps();

            $table->foreign(['user_id', 'user_profile_type'])
                ->references(['user_id', 'user_profile_type'])
                ->on('user_profiles')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        Capsule::schema()->create('nodes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('region_code');
            $table->integer('division_id')->unsigned();
            $table->string('name');
            $table->timestamps();
        });

        Capsule::schema()->create('node_links', function (Blueprint $table) {
            $table->increments('id');
            $table->string('left_region_code');
            $table->integer('left_division_id')->unsigned();
            $table->string('right_region_code');
            $table->integer('right_division_id')->unsigned();
            $table->timestamps();
        });

        Capsule::schema()->create('tenant_users', function (Blueprint $table) {
            $table->string('id');
            $table->string('tenant_id');
            $table->string('name')->nullable();
            $table->primary(['id', 'tenant_id']);
        });

        Capsule::schema()->create('three_col_users', function (Blueprint $table) {
            $table->string('id');
            $table->string('tenant_id');
            $table->string('region_id');
            $table->string('name')->nullable();
            $table->primary(['id', 'tenant_id', 'region_id']);
        });

        Capsule::schema()->create('three_col_user_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('tenant_id');
            $table->string('region_id');
            $table->string('label')->nullable();
        });

        Capsule::schema()->create('soft_delete_tenant_users', function (Blueprint $table) {
            $table->string('id');
            $table->string('tenant_id');
            $table->string('name')->nullable();
            $table->softDeletes();
            $table->primary(['id', 'tenant_id']);
        });

        Capsule::schema()->create('enum_tenant_users', function (Blueprint $table) {
            $table->string('id');
            $table->string('tenant_id');
            $table->string('name')->nullable();
            $table->primary(['id', 'tenant_id']);
        });

        Capsule::schema()->create('scoped_users', function (Blueprint $table) {
            $table->string('id');
            $table->string('scope_id')->nullable();
            $table->string('name')->nullable();
            $table->unique(['id', 'scope_id']);
        });

        Capsule::schema()->create('coded_users', function (Blueprint $table) {
            $table->string('code');
            $table->string('tenant_id');
            $table->string('name')->nullable();
            $table->primary(['code', 'tenant_id']);
        });

        Capsule::schema()->create('tenant_user_notes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tenant_user_id');
            $table->string('tenant_id');
            $table->string('note');
        });
    }
}
