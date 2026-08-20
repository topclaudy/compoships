<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Enums\TenantEnum;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\CodedUser;
use Awobaz\Compoships\Tests\Models\EnumTenantUser;
use Awobaz\Compoships\Tests\Models\MisconfiguredTenantUser;
use Awobaz\Compoships\Tests\Models\ScopedUser;
use Awobaz\Compoships\Tests\Models\SoftDeleteTenantUser;
use Awobaz\Compoships\Tests\Models\TenantUser;
use Awobaz\Compoships\Tests\Models\ThreeColUser;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\Awobaz\Compoships\Compoships::class)]
class CompositeKeyWriteTest extends TestCase
{
    public function test_update_includes_composite_key_in_where()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $alice->name = 'Alice2';
        $alice->save();

        $rows = Capsule::table('tenant_users')->orderBy('tenant_id')->get();
        $this->assertSame('Alice2', $rows[0]->name);
        $this->assertSame('Bob', $rows[1]->name);
    }

    public function test_delete_includes_composite_key_in_where()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first()->delete();

        $rows = Capsule::table('tenant_users')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('t2', $rows[0]->tenant_id);
    }

    public function test_refresh_includes_composite_key_in_where()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        Capsule::table('tenant_users')
            ->where('id', 'u1')
            ->where('tenant_id', 't1')
            ->update(['name' => 'Mutated']);

        $alice->refresh();

        $this->assertSame('Mutated', $alice->name);
    }

    public function test_save_uses_original_composite_value_after_inmemory_mutation()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $alice->tenant_id = 't99';
        $alice->name = 'Alice2';
        $alice->save();

        $rows = Capsule::table('tenant_users')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('u1', $rows[0]->id);
        $this->assertSame('t99', $rows[0]->tenant_id);
        $this->assertSame('Alice2', $rows[0]->name);
    }

    public function test_default_behavior_unchanged_when_composite_key_empty()
    {
        $allocation = new Allocation();
        $allocation->user_id = 1;
        $allocation->booking_id = 2;
        $allocation->vehicle_id = 3;
        $allocation->save();

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $allocation->user_id = 99;
        $allocation->save();

        $log = Capsule::connection()->getQueryLog();
        Capsule::connection()->disableQueryLog();

        $updateEntry = collect($log)->first(fn ($q) => stripos($q['query'], 'update') === 0);
        $this->assertNotNull($updateEntry, 'no update query was captured');

        $wherePosition = stripos($updateEntry['query'], 'where');
        $this->assertNotFalse($wherePosition, 'update query has no WHERE clause');

        $whereClause = substr($updateEntry['query'], $wherePosition);
        $this->assertStringContainsString('"id" = ?', $whereClause);
        $this->assertStringNotContainsString('"tenant_id"', $whereClause);
    }

    public function test_three_column_composite_key()
    {
        Capsule::table('three_col_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'region_id' => 'r1', 'name' => 'Alpha'],
            ['id' => 'u1', 'tenant_id' => 't1', 'region_id' => 'r2', 'name' => 'Beta'],
        ]);

        $row = ThreeColUser::where('id', 'u1')
            ->where('tenant_id', 't1')
            ->where('region_id', 'r1')
            ->first();
        $row->name = 'Alpha2';
        $row->save();

        $rows = Capsule::table('three_col_users')->orderBy('region_id')->get();
        $this->assertSame('Alpha2', $rows[0]->name);
        $this->assertSame('Beta', $rows[1]->name);
    }

    public function test_compositekey_missing_scalar_primary_key_throws()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $row = MisconfiguredTenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $row->name = 'X';

        try {
            $row->save();
            $this->fail('Expected InvalidUsageException was not thrown');
        } catch (InvalidUsageException $e) {
            $this->assertStringContainsString(MisconfiguredTenantUser::class, $e->getMessage());
            $this->assertStringContainsString('"id"', $e->getMessage());
        }
    }

    public function test_soft_delete_uses_composite_key_in_where()
    {
        Capsule::table('soft_delete_tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice', 'deleted_at' => null],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob', 'deleted_at' => null],
        ]);

        SoftDeleteTenantUser::where('id', 'u1')->where('tenant_id', 't1')->first()->delete();

        $aliceRow = Capsule::table('soft_delete_tenant_users')
            ->where('id', 'u1')
            ->where('tenant_id', 't1')
            ->first();
        $bobRow = Capsule::table('soft_delete_tenant_users')
            ->where('id', 'u1')
            ->where('tenant_id', 't2')
            ->first();

        $this->assertNotNull($aliceRow->deleted_at);
        $this->assertNull($bobRow->deleted_at);
    }

    public function test_backed_enum_composite_column_binds_raw_value()
    {
        Capsule::table('enum_tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
        ]);

        $row = EnumTenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $this->assertInstanceOf(TenantEnum::class, $row->tenant_id);

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $row->name = 'Alice2';
        $row->save();

        $log = Capsule::connection()->getQueryLog();
        Capsule::connection()->disableQueryLog();

        $updateEntry = collect($log)->first(fn ($q) => stripos($q['query'], 'update') === 0);
        $this->assertNotNull($updateEntry, 'no update query was captured');

        foreach ($updateEntry['bindings'] as $binding) {
            $this->assertNotInstanceOf(TenantEnum::class, $binding, 'enum object leaked into bindings');
        }
        $this->assertContains('t1', $updateEntry['bindings'], 'raw tenant_id value missing from bindings');
    }

    public function test_scalar_primary_key_contract_preserved()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $this->assertSame('u1', $alice->getKey());
        $this->assertIsString($alice->getKey());

        Capsule::connection()->flushQueryLog();
        Capsule::connection()->enableQueryLog();

        $found = TenantUser::find('u1');

        $log = Capsule::connection()->getQueryLog();
        Capsule::connection()->disableQueryLog();

        $this->assertInstanceOf(TenantUser::class, $found);

        $selectEntry = collect($log)->first(fn ($q) => stripos($q['query'], 'select') === 0);
        $this->assertNotNull($selectEntry);
        $this->assertStringContainsString('"id" = ?', $selectEntry['query']);
        $this->assertStringNotContainsString('"tenant_id"', $selectEntry['query']);
    }

    public function test_null_composite_column_uses_is_null()
    {
        Capsule::table('scoped_users')->insert([
            ['id' => '1', 'scope_id' => null, 'name' => 'Alice'],
            ['id' => '1', 'scope_id' => 'x',  'name' => 'Bob'],
        ]);

        $alice = ScopedUser::where('id', '1')->whereNull('scope_id')->first();
        $alice->name = 'Alice2';
        $alice->save();

        $aliceRow = Capsule::table('scoped_users')->where('id', '1')->whereNull('scope_id')->first();
        $bobRow = Capsule::table('scoped_users')->where('id', '1')->where('scope_id', 'x')->first();

        $this->assertSame('Alice2', $aliceRow->name);
        $this->assertSame('Bob', $bobRow->name);
    }

    public function test_refresh_with_null_composite_column_uses_is_null()
    {
        Capsule::table('scoped_users')->insert([
            ['id' => '1', 'scope_id' => null, 'name' => 'Alice'],
            ['id' => '1', 'scope_id' => 'x',  'name' => 'Bob'],
        ]);

        $alice = ScopedUser::where('id', '1')->whereNull('scope_id')->first();
        Capsule::table('scoped_users')
            ->where('id', '1')
            ->whereNull('scope_id')
            ->update(['name' => 'Mutated']);

        $alice->refresh();

        $this->assertSame('Mutated', $alice->name);
    }

    public function test_insert_does_not_trigger_composite_validation()
    {
        $user = new MisconfiguredTenantUser();
        $user->id = 'new';
        $user->tenant_id = 'tx';
        $user->name = 'X';
        $user->save();

        $row = Capsule::table('tenant_users')->where('id', 'new')->first();
        $this->assertNotNull($row);
        $this->assertSame('X', $row->name);
    }

    public function test_update_via_mass_assignment_includes_composite_key_in_where()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        $alice->update(['name' => 'Alice2']);

        $rows = Capsule::table('tenant_users')->orderBy('tenant_id')->get();
        $this->assertSame('Alice2', $rows[0]->name);
        $this->assertSame('Bob', $rows[1]->name);
    }

    public function test_builder_bulk_update_does_not_inject_composite_key()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $affected = TenantUser::where('id', 'u1')->update(['name' => 'BulkX']);

        $this->assertSame(2, $affected);

        $rows = Capsule::table('tenant_users')->orderBy('tenant_id')->get();
        $this->assertSame('BulkX', $rows[0]->name);
        $this->assertSame('BulkX', $rows[1]->name);
    }

    public function test_fresh_includes_composite_key_in_where()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $alice = TenantUser::where('id', 'u1')->where('tenant_id', 't1')->first();
        Capsule::table('tenant_users')
            ->where('id', 'u1')
            ->where('tenant_id', 't1')
            ->update(['name' => 'FreshlyLoaded']);

        $reloaded = $alice->fresh();

        $this->assertInstanceOf(TenantUser::class, $reloaded);
        $this->assertSame('FreshlyLoaded', $reloaded->name);
        $this->assertSame('t1', $reloaded->tenant_id);
    }

    public function test_force_delete_uses_composite_key_in_where()
    {
        Capsule::table('soft_delete_tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice', 'deleted_at' => null],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob', 'deleted_at' => null],
        ]);

        SoftDeleteTenantUser::where('id', 'u1')->where('tenant_id', 't1')->first()->forceDelete();

        $rows = Capsule::table('soft_delete_tenant_users')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('t2', $rows[0]->tenant_id);
    }

    public function test_restore_uses_composite_key_in_where()
    {
        Capsule::table('soft_delete_tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice', 'deleted_at' => '2020-01-01 00:00:00'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob', 'deleted_at' => null],
        ]);

        SoftDeleteTenantUser::withTrashed()
            ->where('id', 'u1')
            ->where('tenant_id', 't1')
            ->first()
            ->restore();

        $aliceRow = Capsule::table('soft_delete_tenant_users')->where('id', 'u1')->where('tenant_id', 't1')->first();
        $bobRow = Capsule::table('soft_delete_tenant_users')->where('id', 'u1')->where('tenant_id', 't2')->first();

        $this->assertNull($aliceRow->deleted_at);
        $this->assertNull($bobRow->deleted_at);
    }

    public function test_save_mutates_composite_column_from_null_to_value()
    {
        Capsule::table('scoped_users')->insert([
            ['id' => '1', 'scope_id' => null, 'name' => 'Alice'],
        ]);

        $alice = ScopedUser::where('id', '1')->whereNull('scope_id')->first();
        $alice->scope_id = 'x';
        $alice->save();

        $originalRow = Capsule::table('scoped_users')->where('id', '1')->whereNull('scope_id')->first();
        $movedRow = Capsule::table('scoped_users')->where('id', '1')->where('scope_id', 'x')->first();

        $this->assertNull($originalRow);
        $this->assertNotNull($movedRow);
        $this->assertSame('Alice', $movedRow->name);
    }

    public function test_save_mutates_composite_column_from_value_to_null()
    {
        Capsule::table('scoped_users')->insert([
            ['id' => '1', 'scope_id' => 'x', 'name' => 'Alice'],
        ]);

        $alice = ScopedUser::where('id', '1')->where('scope_id', 'x')->first();
        $alice->scope_id = null;
        $alice->save();

        $originalRow = Capsule::table('scoped_users')->where('id', '1')->where('scope_id', 'x')->first();
        $movedRow = Capsule::table('scoped_users')->where('id', '1')->whereNull('scope_id')->first();

        $this->assertNull($originalRow);
        $this->assertNotNull($movedRow);
        $this->assertSame('Alice', $movedRow->name);
    }

    public function test_builder_bulk_delete_does_not_inject_composite_key()
    {
        Capsule::table('tenant_users')->insert([
            ['id' => 'u1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['id' => 'u1', 'tenant_id' => 't2', 'name' => 'Bob'],
            ['id' => 'u2', 'tenant_id' => 't1', 'name' => 'Carol'],
        ]);

        $affected = TenantUser::where('id', 'u1')->delete();

        $this->assertSame(2, $affected);

        $rows = Capsule::table('tenant_users')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('u2', $rows[0]->id);
    }

    public function test_non_id_scalar_primary_key_works()
    {
        Capsule::table('coded_users')->insert([
            ['code' => 'c1', 'tenant_id' => 't1', 'name' => 'Alice'],
            ['code' => 'c1', 'tenant_id' => 't2', 'name' => 'Bob'],
        ]);

        $alice = CodedUser::where('code', 'c1')->where('tenant_id', 't1')->first();
        $this->assertSame('c1', $alice->getKey());

        $alice->name = 'Alice2';
        $alice->save();

        $rows = Capsule::table('coded_users')->orderBy('tenant_id')->get();
        $this->assertSame('Alice2', $rows[0]->name);
        $this->assertSame('Bob', $rows[1]->name);
    }
}
