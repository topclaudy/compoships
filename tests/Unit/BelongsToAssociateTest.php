<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Exceptions\InvalidUsageException;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\Models\CodeNote;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BelongsTo::class)]
class BelongsToAssociateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_associate_copies_every_owner_key_and_sets_the_relation()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I']);
        $note = new CodeNote();

        $note->code()->associate($code);

        $this->assertSame('G', $note->group_code);
        $this->assertSame('I', $note->item_code);
        $this->assertTrue($note->relationLoaded('code'));
        $this->assertSame($code, $note->code);
    }

    public function test_dissociate_clears_every_foreign_key_and_unsets_the_relation()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I']);
        $note = CodeNote::create(['group_code' => 'G', 'item_code' => 'I']);
        $note->code;

        $note->code()->dissociate();

        $this->assertNull($note->group_code);
        $this->assertNull($note->item_code);
        $this->assertFalse($note->relationLoaded('code') && $note->getRelation('code') !== null);
        $this->assertNull($note->code);
    }

    public function test_associate_null_behaves_like_dissociate()
    {
        $note = CodeNote::create(['group_code' => 'G', 'item_code' => 'I']);

        $note->code()->associate(null);

        $this->assertNull($note->group_code);
        $this->assertNull($note->item_code);
    }

    public function test_associate_with_a_scalar_is_rejected()
    {
        $note = CodeNote::create(['group_code' => 'G', 'item_code' => 'I']);

        $this->expectException(InvalidUsageException::class);

        $note->code()->associate(5);
    }
}
