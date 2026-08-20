<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\HasOne;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\Models\CodeNote;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HasOne::class)]
class HasOneDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_with_default_closure_receives_the_parent()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I', 'label' => 'widget']);

        $default = $code->firstNoteOrDefault;

        $this->assertInstanceOf(CodeNote::class, $default);
        $this->assertFalse($default->exists);
        $this->assertSame('default for widget', $default->body);
        $this->assertSame('G', $default->group_code);
        $this->assertSame('I', $default->item_code);
    }

    public function test_with_default_applies_on_eager_load()
    {
        Code::create(['group_code' => 'G', 'item_code' => 'I', 'label' => 'widget']);

        $codes = Code::with('firstNoteOrDefault')->get();

        $this->assertSame('default for widget', $codes[0]->firstNoteOrDefault->body);
    }

    public function test_make_returns_an_instance_with_foreign_keys_set()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I']);

        $note = $code->firstNote()->make(['body' => 'draft']);

        $this->assertInstanceOf(CodeNote::class, $note);
        $this->assertSame('G', $note->group_code);
        $this->assertSame('I', $note->item_code);
        $this->assertSame('draft', $note->body);
    }
}
