<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Queue\QueueableCompositeCollection;
use Awobaz\Compoships\Tests\Models\MisconfiguredTenantUser;
use Awobaz\Compoships\Tests\Models\ScopedUser;
use Awobaz\Compoships\Tests\Models\TenantUser;
use Awobaz\Compoships\Tests\Models\ThreeColUser;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\Awobaz\Compoships\Queue\QueueableCompositeCollection::class)]
class QueueableCompositeCollectionTest extends TestCase
{
    public function test_basic_roundtrip_preserves_models_and_order()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u2', 'tenant_id' => 't2', 'name' => 'Bob'],
            ['id' => 'u3', 'tenant_id' => 't3', 'name' => 'Carol'],
        ]);

        $carol = TenantUser::where('id', 'u3')->where('tenant_id', 't3')->first();
        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $bob = TenantUser::where('id', 'u2')->where('tenant_id', 't2')->first();
        $collection = new EloquentCollection([$carol, $alice, $bob]);

        $bag = QueueableCompositeCollection::for($collection);
        $restored = unserialize(serialize($bag));

        $reloaded = $restored->restore();

        $this->assertCount(3, $reloaded);
        $this->assertSame(['Carol', 'Alice', 'Bob'], $reloaded->pluck('name')->all());
    }

    public function test_null_discriminator_roundtrip()
    {
        Capsule::table('scoped_users')->insert([
            ['id' => '1', 'scope_id' => null, 'name' => 'Alice'],
            ['id' => '1', 'scope_id' => 'x', 'name' => 'Bob'],
        ]);

        $alice = ScopedUser::where('id', '1')->whereNull('scope_id')->first();
        $collection = new EloquentCollection([$alice]);

        $bag = QueueableCompositeCollection::for($collection);
        $reloaded = unserialize(serialize($bag))->restore();

        $this->assertCount(1, $reloaded);
        $this->assertSame('Alice', $reloaded[0]->name);
        $this->assertNull($reloaded[0]->scope_id);
    }

    public function test_empty_collection_roundtrip()
    {
        $bag = QueueableCompositeCollection::for(new EloquentCollection());

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $reloaded = unserialize(serialize($bag))->restore();

        $log = Capsule::connection()->getQueryLog();
        Capsule::connection()->disableQueryLog();

        $this->assertCount(0, $reloaded);
        $this->assertSame(0, count($log), 'empty bag must issue no queries on restore');
    }

    public function test_mixed_class_collection_throws()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);
        Capsule::table('scoped_users')->insert([
            ['id' => '1', 'scope_id' => 'x', 'name' => 'Other'],
        ]);

        $tenant = TenantUser::where('id', 'u1')->first();
        $scoped = ScopedUser::where('id', '1')->first();

        try {
            QueueableCompositeCollection::for(new EloquentCollection([$tenant, $scoped]));
            $this->fail('Expected LogicException was not thrown');
        } catch (LogicException $e) {
            $this->assertStringContainsString(TenantUser::class, $e->getMessage());
            $this->assertStringContainsString(ScopedUser::class, $e->getMessage());
        }
    }

    public function test_misconfigured_compositekey_throws()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $bad = MisconfiguredTenantUser::where('id', 'u1')->first();

        try {
            QueueableCompositeCollection::for(new EloquentCollection([$bad]));
            $this->fail('Expected InvalidUsageException was not thrown');
        } catch (InvalidUsageException $e) {
            $this->assertStringContainsString(MisconfiguredTenantUser::class, $e->getMessage());
            $this->assertStringContainsString('"id"', $e->getMessage());
        }
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

        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $alice->load('notes');

        $bag = QueueableCompositeCollection::for(new EloquentCollection([$alice]));
        $reloaded = unserialize(serialize($bag))->restore();

        $this->assertCount(1, $reloaded);
        $this->assertTrue($reloaded[0]->relationLoaded('notes'));
        $this->assertCount(2, $reloaded[0]->notes);
        $this->assertSame(['first', 'second'], $reloaded[0]->notes->pluck('note')->sort()->values()->all());
    }

    public function test_canonical_key_handles_type_mismatched_composite_columns()
    {
        $alice = new TenantUser();
        $alice->id = 'u1';
        $alice->tenant_id = 1;
        $alice->name = 'Alice';
        $alice->save();

        $this->assertIsInt($alice->tenant_id, 'precondition: in-memory tenant_id must be an integer');

        $bag = QueueableCompositeCollection::for(new EloquentCollection([$alice]));
        $reloaded = unserialize(serialize($bag))->restore();

        $reloadedFresh = TenantUser::where('id', 'u1')->first();
        $this->assertIsString($reloadedFresh->tenant_id, 'precondition: PDO returns the column as string');

        $this->assertCount(1, $reloaded, 'restore must not drop the model when the in-memory type differs from the PDO-returned type');
        $this->assertSame('Alice', $reloaded[0]->name);
    }

    public function test_three_column_composite_key_roundtrip()
    {
        Capsule::table('three_col_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'region_id' => 'r1', 'name' => 'Alpha'],
            ['id' => 'u1', 'tenant_id' => 't1', 'region_id' => 'r2', 'name' => 'Beta'],
            ['id' => 'u1', 'tenant_id' => 't1', 'region_id' => 'r3', 'name' => 'Gamma'],
        ]);

        $beta = ThreeColUser::where('id', 'u1')->where('tenant_id', 't1')->where('region_id', 'r2')->first();
        $alpha = ThreeColUser::where('id', 'u1')->where('tenant_id', 't1')->where('region_id', 'r1')->first();
        $collection = new EloquentCollection([$beta, $alpha]);

        $bag = QueueableCompositeCollection::for($collection);
        $reloaded = unserialize(serialize($bag))->restore();

        $this->assertCount(2, $reloaded);
        $this->assertSame(['Beta', 'Alpha'], $reloaded->pluck('name')->all());
    }
}
