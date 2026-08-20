<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\Models\CodeNote;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HasOneOrMany::class)]
class HasManyNullAndCollisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_eager_load_matches_lazy_load_when_a_key_component_is_null()
    {
        $allocation = Allocation::create(['booking_id' => 1, 'vehicle_id' => null]);
        $allocation->trackingTasks()->create([]);

        $lazy = Allocation::find($allocation->id)->trackingTasks;
        $eager = Allocation::with('trackingTasks')->find($allocation->id)->trackingTasks;

        $this->assertCount(1, $lazy);
        $this->assertCount(1, $eager);
        $this->assertEquals($lazy->modelKeys(), $eager->modelKeys());
    }

    public function test_eager_load_with_mixed_null_free_and_null_containing_keys()
    {
        $first = Allocation::create(['booking_id' => 1, 'vehicle_id' => 2]);
        $second = Allocation::create(['booking_id' => 3, 'vehicle_id' => null]);
        $first->trackingTasks()->create([]);
        $second->trackingTasks()->create([]);

        $allocations = Allocation::with('trackingTasks')->orderBy('id')->get();

        $this->assertCount(1, $allocations[0]->trackingTasks);
        $this->assertCount(1, $allocations[1]->trackingTasks);
        $this->assertSame(1, $allocations[0]->trackingTasks[0]->booking_id);
        $this->assertNull($allocations[1]->trackingTasks[0]->vehicle_id);
    }

    public function test_all_null_parent_keys_skip_the_relation_query()
    {
        Allocation::create(['booking_id' => null, 'vehicle_id' => null]);
        Allocation::create(['booking_id' => null, 'vehicle_id' => null]);

        Capsule::connection()->enableQueryLog();
        $allocations = Allocation::with('trackingTasks')->get();
        $queries = array_column(Capsule::connection()->getQueryLog(), 'query');

        $this->assertCount(2, $allocations);
        $this->assertTrue($allocations[0]->relationLoaded('trackingTasks'));
        $this->assertCount(0, $allocations[0]->trackingTasks);
        $this->assertCount(1, $queries, 'only the parent query should run: '.implode(' | ', $queries));
    }

    public function test_key_components_containing_dashes_do_not_cross_match()
    {
        Code::create(['group_code' => 'A-1', 'item_code' => '2', 'label' => 'first']);
        Code::create(['group_code' => 'A', 'item_code' => '1-2', 'label' => 'second']);
        CodeNote::create(['group_code' => 'A', 'item_code' => '1-2', 'body' => 'belongs to second']);

        $codes = Code::with('notes')->orderBy('label')->get();

        $this->assertSame('first', $codes[0]->label);
        $this->assertCount(0, $codes[0]->notes);
        $this->assertCount(1, $codes[1]->notes);
    }

    public function test_null_and_empty_string_components_do_not_cross_match()
    {
        Code::create(['group_code' => null, 'item_code' => 'x', 'label' => 'null-group']);
        Code::create(['group_code' => '', 'item_code' => 'x', 'label' => 'empty-group']);
        CodeNote::create(['group_code' => '', 'item_code' => 'x', 'body' => 'belongs to empty']);

        $codes = Code::with('notes')->orderBy('label')->get();

        $this->assertSame('empty-group', $codes[0]->label);
        $this->assertCount(1, $codes[0]->notes);
        $this->assertCount(0, $codes[1]->notes);
    }
}
