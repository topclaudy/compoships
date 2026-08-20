<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\Models\CodeNote;
use Awobaz\Compoships\Tests\Models\TouchingCodeNote;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BelongsTo::class)]
class BelongsToCompareAndTouchTest extends TestCase
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
        }, E_WARNING | E_NOTICE | E_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();

        parent::tearDown();
    }

    public function test_is_and_is_not_compare_every_key_component()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => '1']);
        $other = Code::create(['group_code' => 'G', 'item_code' => '2']);
        $note = CodeNote::create(['group_code' => 'G', 'item_code' => 1]);

        $this->assertTrue($note->code()->is($code), 'integer 1 must match string "1"');
        $this->assertFalse($note->code()->isNot($code));
        $this->assertFalse($note->code()->is($other));
        $this->assertTrue($note->code()->isNot($other));
        $this->assertFalse($note->code()->is(null));
        $this->assertSame([], $this->warnings);
    }

    public function test_is_returns_false_when_the_child_keys_are_all_null()
    {
        $code = Code::create(['group_code' => null, 'item_code' => null]);
        $note = CodeNote::create(['group_code' => null, 'item_code' => null]);

        $this->assertFalse($note->code()->is($code));
    }

    public function test_touches_updates_the_composite_parent()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I']);
        $before = $code->updated_at->toDateTimeString();

        Carbon::setTestNow('2021-01-01 00:00:00');
        TouchingCodeNote::create(['group_code' => 'G', 'item_code' => 'I', 'body' => 'x']);

        $this->assertSame('2021-01-01 00:00:00', Capsule::table('codes')->where('id', $code->id)->value('updated_at'));
        $this->assertNotSame($before, Capsule::table('codes')->where('id', $code->id)->value('updated_at'));
        $this->assertSame([], $this->warnings);
    }

    public function test_relation_touch_updates_only_the_owner()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I']);
        $other = Code::create(['group_code' => 'G', 'item_code' => 'J']);
        $note = CodeNote::create(['group_code' => 'G', 'item_code' => 'I']);

        Carbon::setTestNow('2021-01-01 00:00:00');
        $note->code()->touch();

        $this->assertSame('2021-01-01 00:00:00', Capsule::table('codes')->where('id', $code->id)->value('updated_at'));
        $this->assertSame('2020-10-29 23:59:59', Capsule::table('codes')->where('id', $other->id)->value('updated_at'));
        $this->assertSame([], $this->warnings);
    }
}
