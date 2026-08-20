<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\Models\CodeNote;
use Awobaz\Compoships\Tests\Models\OriginalPackage;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HasOneOrMany::class)]
class HasOneOrManyWriteHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_create_applies_with_attributes_on_a_single_column_relation()
    {
        $allocation = Allocation::create([]);

        $package = $allocation->originalPackages()->withAttributes(['name' => 'from-relation'])->create([]);

        $this->assertSame('from-relation', $package->name);
        $this->assertSame('from-relation', Capsule::table('original_packages')->value('name'));
    }

    public function test_save_applies_with_attributes_on_a_composite_relation()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I']);

        $note = $code->notes()->withAttributes(['body' => 'from-relation'])->save(new CodeNote());

        $this->assertSame('G', $note->group_code);
        $this->assertSame('I', $note->item_code);
        $this->assertSame('from-relation', $note->body);
        $this->assertSame('from-relation', Capsule::table('code_notes')->value('body'));
    }

    public function test_caller_attributes_take_precedence_over_with_attributes()
    {
        $allocation = Allocation::create([]);

        $package = $allocation->originalPackages()->withAttributes(['name' => 'default'])->create(['name' => 'explicit']);

        $this->assertSame('explicit', $package->name);
    }

    public function test_create_sets_the_inverse_relation()
    {
        $allocation = Allocation::create([]);

        $package = $allocation->originalPackages()->create([]);

        $this->assertTrue($package->relationLoaded('allocation'));
        $this->assertSame($allocation, $package->allocation);
    }

    public function test_force_create_on_a_composite_relation_sets_every_foreign_key()
    {
        $allocation = Allocation::create(['booking_id' => 3, 'vehicle_id' => 4]);

        $task = $allocation->trackingTasks()->forceCreate([]);

        $this->assertTrue($task->exists);
        $this->assertSame(3, $task->booking_id);
        $this->assertSame(4, $task->vehicle_id);
        $row = Capsule::table('tracking_tasks')->first();
        $this->assertSame(3, (int) $row->booking_id);
        $this->assertSame(4, (int) $row->vehicle_id);
    }
}
