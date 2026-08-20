<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\Models\CodeNote;
use Awobaz\Compoships\Tests\Models\TrackingTask;
use Awobaz\Compoships\Tests\Models\User;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BelongsTo::class)]
class BelongsToConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_unsaved_child_without_foreign_keys_resolves_null_without_a_query()
    {
        User::create(['booking_id' => 9]);

        Capsule::connection()->enableQueryLog();
        $parent = (new Allocation())->user;

        $this->assertNull($parent);
        $this->assertSame([], Capsule::connection()->getQueryLog());
    }

    public function test_child_loaded_without_foreign_key_columns_resolves_null_without_a_query()
    {
        $user = User::create(['booking_id' => 9]);
        $allocation = Allocation::create(['user_id' => $user->id, 'booking_id' => 9]);

        $partial = Allocation::select('id')->find($allocation->id);

        Capsule::connection()->enableQueryLog();
        $this->assertNull($partial->user);
        $this->assertSame([], Capsule::connection()->getQueryLog());
    }

    public function test_partially_null_foreign_key_constrains_with_is_null()
    {
        $match = Allocation::create(['booking_id' => 1, 'vehicle_id' => null]);
        Allocation::create(['booking_id' => 1, 'vehicle_id' => 7]);
        $task = TrackingTask::create(['booking_id' => 1, 'vehicle_id' => null]);

        $sql = $task->allocation()->toSql();

        $this->assertStringContainsString('"allocations"."vehicle_id" is null', $sql);
        $this->assertStringNotContainsString('is not null', $sql);
        $this->assertSame($match->id, $task->allocation->id);
    }

    public function test_null_detection_uses_the_child_foreign_key_columns()
    {
        $user = User::create(['booking_id' => null]);
        $allocation = new Allocation(['user_id' => $user->id, 'booking_id' => null]);

        $sql = $allocation->user()->toSql();

        $this->assertStringContainsString('"users"."id" = ?', $sql);
        $this->assertStringContainsString('"users"."booking_id" is null', $sql);
        $this->assertStringNotContainsString('is not null', $sql);
        $this->assertSame($user->id, $allocation->user->id);
    }

    public function test_all_null_foreign_keys_resolve_null_without_a_query()
    {
        Code::create(['group_code' => null, 'item_code' => null]);
        $note = CodeNote::create(['group_code' => null, 'item_code' => null]);

        Capsule::connection()->enableQueryLog();
        $this->assertNull($note->code);
        $this->assertSame([], Capsule::connection()->getQueryLog());
    }
}
