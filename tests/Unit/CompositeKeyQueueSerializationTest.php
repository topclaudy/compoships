<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Enums\TenantEnum;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\EnumTenantUser;
use Awobaz\Compoships\Tests\Models\MisconfiguredTenantUser;
use Awobaz\Compoships\Tests\Models\ScopedUser;
use Awobaz\Compoships\Tests\Models\TenantUser;
use Awobaz\Compoships\Tests\Models\ThreeColUser;
use Awobaz\Compoships\Tests\Stubs\QueueableJobStub;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\Awobaz\Compoships\Compoships::class)]
class CompositeKeyQueueSerializationTest extends TestCase
{
    public function test_basic_roundtrip_preserves_composite_identity()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $original = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $job = new QueueableJobStub($original);

        $restored = unserialize(serialize($job));

        $this->assertInstanceOf(TenantUser::class, $restored->model);
        $this->assertSame('u1', $restored->model->id);
        $this->assertSame('t1', $restored->model->tenant_id);
        $this->assertSame('Alice', $restored->model->name);
    }

    public function test_null_discriminator_roundtrips_correctly()
    {
        Capsule::table('scoped_users')->insert([
            ['id' => '1', 'scope_id' => null, 'name' => 'Alice'],
            ['id' => '1', 'scope_id' => 'x', 'name' => 'Bob'],
        ]);

        $original = ScopedUser::where('id', '1')->whereNull('scope_id')->first();
        $job = new QueueableJobStub($original);

        $restored = unserialize(serialize($job));

        $this->assertInstanceOf(ScopedUser::class, $restored->model);
        $this->assertSame('1', $restored->model->id);
        $this->assertNull($restored->model->scope_id);
        $this->assertSame('Alice', $restored->model->name);
    }

    public function test_three_column_composite_key_roundtrips()
    {
        Capsule::table('three_col_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'region_id' => 'r1', 'name' => 'Alpha'],
            ['id' => 'u1', 'tenant_id' => 't1', 'region_id' => 'r2', 'name' => 'Beta'],
        ]);

        $original = ThreeColUser::where('id', 'u1')
            ->where('tenant_id', 't1')
            ->where('region_id', 'r1')
            ->first();
        $job = new QueueableJobStub($original);

        $restored = unserialize(serialize($job));

        $this->assertInstanceOf(ThreeColUser::class, $restored->model);
        $this->assertSame('r1', $restored->model->region_id);
        $this->assertSame('Alpha', $restored->model->name);
    }

    public function test_scalar_model_uses_parent_serialization_path()
    {
        $allocation = new Allocation();
        $allocation->user_id = 1;
        $allocation->booking_id = 2;
        $allocation->vehicle_id = 3;
        $allocation->save();

        $queueableId = $allocation->getQueueableId();
        $this->assertSame($allocation->getKey(), $queueableId);
        $this->assertIsInt($queueableId);

        $job = new QueueableJobStub($allocation);
        $restored = unserialize(serialize($job));

        $this->assertInstanceOf(Allocation::class, $restored->model);
        $this->assertSame($allocation->getKey(), $restored->model->getKey());
    }

    public function test_misconfigured_composite_key_throws_on_serialize()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $original = MisconfiguredTenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $job = new QueueableJobStub($original);

        try {
            serialize($job);
            $this->fail('Expected InvalidUsageException was not thrown');
        } catch (InvalidUsageException $e) {
            $this->assertStringContainsString(MisconfiguredTenantUser::class, $e->getMessage());
            $this->assertStringContainsString('"id"', $e->getMessage());
        }
    }

    public function test_scalar_id_payload_falls_through_to_parent()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $instance = new TenantUser();
        $restored = $instance->newQueryForRestoration('u1')->first();

        $this->assertInstanceOf(TenantUser::class, $restored);
        $this->assertSame('u1', $restored->id);
        $this->assertContains($restored->tenant_id, ['t1', 't2']);
    }

    public function test_getQueueableId_returns_json_encoded_composite_key()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $original = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();

        $id = $original->getQueueableId();

        $this->assertIsString($id);
        $decoded = json_decode($id, true);
        $this->assertSame(['id' => 'u1', 'tenant_id' => 't1'], $decoded);
    }

    public function test_newQueryForRestoration_falls_through_on_wrong_shape_json()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $instance = new TenantUser();
        $bogusId = '{"id":"u1","tenant_id":"t1","extra":"x"}';

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $instance->newQueryForRestoration($bogusId)->first();

        $log = Capsule::connection()->getQueryLog();
        Capsule::connection()->disableQueryLog();

        $selectEntry = collect($log)->first(fn ($q) => stripos($q['query'], 'select') === 0);
        $this->assertNotNull($selectEntry);
        $this->assertStringContainsString('"id" = ?', $selectEntry['query']);
        $this->assertStringNotContainsString('"tenant_id"', $selectEntry['query']);
    }

    public function test_newQueryForRestoration_falls_through_on_non_json_string()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $instance = new TenantUser();

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $instance->newQueryForRestoration('not-json-at-all')->first();

        $log = Capsule::connection()->getQueryLog();
        Capsule::connection()->disableQueryLog();

        $selectEntry = collect($log)->first(fn ($q) => stripos($q['query'], 'select') === 0);
        $this->assertNotNull($selectEntry);
        $this->assertStringContainsString('"id" = ?', $selectEntry['query']);
        $this->assertStringNotContainsString('"tenant_id"', $selectEntry['query']);
    }

    public function test_newQueryForRestoration_falls_through_on_integer_id()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => '123', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $instance = new TenantUser();

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $instance->newQueryForRestoration(123)->first();

        $log = Capsule::connection()->getQueryLog();
        Capsule::connection()->disableQueryLog();

        $selectEntry = collect($log)->first(fn ($q) => stripos($q['query'], 'select') === 0);
        $this->assertNotNull($selectEntry);
        $this->assertStringContainsString('"id" = ?', $selectEntry['query']);
        $this->assertStringNotContainsString('"tenant_id"', $selectEntry['query']);
    }

    public function test_multiple_composite_models_on_job_roundtrip()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
            ['id' => 'u2', 'tenant_id' => 't1', 'name' => 'Carol'],
        ]);

        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $carol = TenantUser::where('id', 'u2')->where('tenant_id', 't1')->first();

        $job = new QueueableJobStub($alice, $carol);
        $restored = unserialize(serialize($job));

        $this->assertInstanceOf(TenantUser::class, $restored->model);
        $this->assertSame('Alice', $restored->model->name);
        $this->assertSame('t1', $restored->model->tenant_id);

        $this->assertInstanceOf(TenantUser::class, $restored->secondModel);
        $this->assertSame('Carol', $restored->secondModel->name);
        $this->assertSame('u2', $restored->secondModel->id);
    }

    public function test_loaded_relations_survive_roundtrip()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);
        Capsule::table('tenant_user_notes')->insert([
            ['tenant_user_id' => 'u1', 'tenant_id' => 't1', 'note' => 'first'],
            ['tenant_user_id' => 'u1', 'tenant_id' => 't1', 'note' => 'second'],
            ['tenant_user_id' => 'u1', 'tenant_id' => 't2', 'note' => 'bob-only'],
        ]);

        $user = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $user->load('notes');
        $this->assertCount(2, $user->notes);

        $job = new QueueableJobStub($user);
        $restored = unserialize(serialize($job));

        $this->assertTrue($restored->model->relationLoaded('notes'));
        $this->assertCount(2, $restored->model->notes);

        $noteTexts = $restored->model->notes->pluck('note')->sort()->values()->all();
        $this->assertSame(['first', 'second'], $noteTexts);
    }

    public function test_backed_enum_composite_column_roundtrip()
    {
        Capsule::table('enum_tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $user = EnumTenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $this->assertInstanceOf(TenantEnum::class, $user->tenant_id);
        $this->assertSame('t1', $user->tenant_id->value);

        $job = new QueueableJobStub($user);
        $restored = unserialize(serialize($job));

        $this->assertInstanceOf(EnumTenantUser::class, $restored->model);
        $this->assertSame('Alice', $restored->model->name);
        $this->assertInstanceOf(TenantEnum::class, $restored->model->tenant_id);
        $this->assertSame('t1', $restored->model->tenant_id->value);
    }
}
