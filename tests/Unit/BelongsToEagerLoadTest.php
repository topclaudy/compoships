<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\Models\CodeNote;
use Awobaz\Compoships\Tests\Models\TrackingTask;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BelongsTo::class)]
class BelongsToEagerLoadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_eager_load_matches_lazy_load_when_a_foreign_key_component_is_null()
    {
        $allocation = Allocation::create(['booking_id' => 5, 'vehicle_id' => null]);
        Allocation::create(['booking_id' => 5, 'vehicle_id' => 8]);
        $task = TrackingTask::create(['booking_id' => 5, 'vehicle_id' => null]);

        $lazy = TrackingTask::find($task->id)->allocation;
        $eager = TrackingTask::with('allocation')->find($task->id)->allocation;

        $this->assertSame($allocation->id, $lazy->id);
        $this->assertNotNull($eager);
        $this->assertSame($allocation->id, $eager->id);
    }

    public function test_all_null_foreign_keys_skip_the_parent_query()
    {
        Code::create(['group_code' => null, 'item_code' => null]);
        CodeNote::create(['group_code' => null, 'item_code' => null]);
        CodeNote::create(['group_code' => null, 'item_code' => null]);

        Capsule::connection()->enableQueryLog();
        $notes = CodeNote::with('code')->get();
        $queries = array_column(Capsule::connection()->getQueryLog(), 'query');

        $this->assertCount(2, $notes);
        $this->assertTrue($notes[0]->relationLoaded('code'));
        $this->assertNull($notes[0]->code);
        $this->assertCount(1, $queries, implode(' | ', $queries));
    }

    public function test_key_components_containing_dashes_do_not_cross_match()
    {
        $first = Code::create(['group_code' => 'A-1', 'item_code' => '2', 'label' => 'first']);
        $second = Code::create(['group_code' => 'A', 'item_code' => '1-2', 'label' => 'second']);
        CodeNote::create(['group_code' => 'A-1', 'item_code' => '2', 'body' => 'for first']);
        CodeNote::create(['group_code' => 'A', 'item_code' => '1-2', 'body' => 'for second']);

        $notes = CodeNote::with('code')->orderBy('body')->get();

        $this->assertSame($first->id, $notes[0]->code->id);
        $this->assertSame($second->id, $notes[1]->code->id);
    }
}
