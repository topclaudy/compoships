<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BelongsTo::class)]
class BelongsToSelfRelationTest extends TestCase
{
    /** @var array<int, string> */
    private array $warnings = [];

    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->warnings = [];
        set_error_handler(function (int $errno, string $errstr): bool {
            $this->warnings[] = $errstr;

            return true;
        }, E_WARNING | E_NOTICE);

        Code::create(['group_code' => 'G', 'item_code' => 'root', 'label' => 'root']);
        Code::create(['group_code' => 'G', 'item_code' => 'child', 'parent_group_code' => 'G', 'parent_item_code' => 'root', 'label' => 'child']);
        Code::create(['group_code' => 'G', 'item_code' => 'orphan', 'parent_group_code' => 'G', 'parent_item_code' => 'missing', 'label' => 'orphan']);
    }

    protected function tearDown(): void
    {
        restore_error_handler();

        parent::tearDown();
    }

    public function test_has_on_a_self_referencing_composite_belongs_to()
    {
        $labels = Code::has('parentCode')->pluck('label')->all();

        $this->assertSame(['child'], $labels);
        $this->assertSame([], $this->warnings);
    }

    public function test_doesnt_have_and_where_has_on_a_self_referencing_composite_belongs_to()
    {
        $this->assertEqualsCanonicalizing(['root', 'orphan'], Code::doesntHave('parentCode')->pluck('label')->all());
        $this->assertSame(['child'], Code::whereHas('parentCode', fn ($q) => $q->where('label', 'root'))->pluck('label')->all());
        $this->assertSame([], $this->warnings);
    }

    public function test_with_count_on_a_self_referencing_composite_belongs_to()
    {
        $counts = Code::withCount('parentCode')->orderBy('id')->pluck('parent_code_count')->all();

        $this->assertSame([0, 1, 0], array_map('intval', $counts));
    }

    public function test_lazy_and_eager_loading_of_the_self_relation()
    {
        $child = Code::where('label', 'child')->first();
        $this->assertSame('root', $child->parentCode->label);

        $eager = Code::with('parentCode')->orderBy('id')->get();
        $this->assertNull($eager[0]->parentCode);
        $this->assertSame('root', $eager[1]->parentCode->label);
        $this->assertNull($eager[2]->parentCode);
    }
}
