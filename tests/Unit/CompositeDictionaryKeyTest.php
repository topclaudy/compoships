<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Concerns\ResolvesBackedEnumValues;
use Awobaz\Compoships\Tests\Enums\PivotRole;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolvesBackedEnumValues::class)]
class CompositeDictionaryKeyTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new class() {
            use ResolvesBackedEnumValues;

            public function key(array $values): string
            {
                return $this->compositeDictionaryKey($values);
            }
        };
    }

    public function test_components_containing_dashes_do_not_collide()
    {
        $this->assertNotSame($this->subject->key(['A-1', 2]), $this->subject->key(['A', '1-2']));
    }

    public function test_null_and_empty_string_do_not_collide()
    {
        $this->assertNotSame($this->subject->key([null, 1]), $this->subject->key(['', 1]));
    }

    public function test_int_and_numeric_string_are_equal()
    {
        $this->assertSame($this->subject->key([1, 'x']), $this->subject->key(['1', 'x']));
    }

    public function test_backed_enum_equals_its_backing_value()
    {
        $this->assertSame($this->subject->key([PivotRole::Lead, 1]), $this->subject->key([PivotRole::Lead->value, '1']));
    }

    public function test_booleans_normalise_like_bindings()
    {
        $this->assertSame($this->subject->key([true, false]), $this->subject->key(['1', '0']));
        $this->assertNotSame($this->subject->key([false, 1]), $this->subject->key(['', 1]));
    }
}
