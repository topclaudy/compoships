<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\HasMany;
use Awobaz\Compoships\Database\Eloquent\Relations\HasOne;
use Awobaz\Compoships\Tests\Models\Code;
use Awobaz\Compoships\Tests\Models\CodeNote;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HasMany::class)]
class HasManyOneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    public function test_one_returns_the_package_has_one()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I']);

        $this->assertInstanceOf(HasOne::class, $code->notes()->one());
    }

    public function test_one_latest_of_many_eager_loads_on_a_composite_relation()
    {
        $code = Code::create(['group_code' => 'G', 'item_code' => 'I']);
        $other = Code::create(['group_code' => 'G', 'item_code' => 'J']);
        Carbon::setTestNow('2020-10-29 10:00:00');
        CodeNote::create(['group_code' => 'G', 'item_code' => 'I', 'body' => 'old']);
        CodeNote::create(['group_code' => 'G', 'item_code' => 'J', 'body' => 'other-old']);
        Carbon::setTestNow('2020-10-29 12:00:00');
        CodeNote::create(['group_code' => 'G', 'item_code' => 'I', 'body' => 'new']);
        CodeNote::create(['group_code' => 'G', 'item_code' => 'J', 'body' => 'other-new']);

        $codes = Code::with('latestNote')->orderBy('id')->get();

        $this->assertSame('new', $codes[0]->latestNote->body);
        $this->assertSame('other-new', $codes[1]->latestNote->body);
        $this->assertSame('new', Code::find($code->id)->latestNote->body);
        $this->assertSame('other-new', Code::find($other->id)->latestNote->body);
    }
}
