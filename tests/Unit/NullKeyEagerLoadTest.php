<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Awobaz\Compoships\Database\Eloquent\Relations\HasOneOrMany;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\OriginalPackage;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * PHP 8.5 deprecates using null as an array offset. Eager loading builds a
 * dictionary keyed by the relation key, so a null single-column key must not
 * reach an array offset. These tests fail on compoships 3.0.2 and pass from
 * 3.1.0 (commit f5db84e).
 */
#[CoversClass(BelongsTo::class)]
#[CoversClass(HasOneOrMany::class)]
class NullKeyEagerLoadTest extends TestCase
{
    /** @var array<int, string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->deprecations = [];

        set_error_handler(function (int $errno, string $errstr): bool {
            $this->deprecations[] = $errstr;

            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();

        parent::tearDown();
    }

    public function test_eager_loading_belongs_to_with_null_foreign_key_resolves_null_without_deprecation()
    {
        Model::unguard();

        $allocation = Allocation::create([]);
        OriginalPackage::create(['allocation_id' => $allocation->id, 'pcid' => null]);

        $packages = OriginalPackage::with('productCode')->get();

        $this->assertCount(1, $packages);
        $this->assertTrue($packages[0]->relationLoaded('productCode'));
        $this->assertNull($packages[0]->productCode);
        $this->assertSame([], $this->deprecations);
    }

    public function test_eager_loading_has_one_with_null_local_key_resolves_null_without_deprecation()
    {
        Model::unguard();

        Allocation::create(['booking_id' => null]);

        $allocations = Allocation::with('space')->get();

        $this->assertCount(1, $allocations);
        $this->assertTrue($allocations[0]->relationLoaded('space'));
        $this->assertNull($allocations[0]->space);
        $this->assertSame([], $this->deprecations);
    }
}
