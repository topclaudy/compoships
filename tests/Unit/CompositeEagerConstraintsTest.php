<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Concerns\BuildsCompositeEagerConstraints;
use Awobaz\Compoships\Concerns\ResolvesBackedEnumValues;
use Awobaz\Compoships\Tests\Enums\PivotRole;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BuildsCompositeEagerConstraints::class)]
class CompositeEagerConstraintsTest extends TestCase
{
    private const COLUMNS = ['allocations.booking_id', 'allocations.vehicle_id'];

    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class() {
            use BuildsCompositeEagerConstraints;
            use ResolvesBackedEnumValues;

            public function apply($query, array $columns, array $tuples): bool
            {
                return $this->addCompositeKeyConstraints($query, $columns, $tuples);
            }
        };
    }

    public function test_null_free_tuples_use_a_single_tuple_in()
    {
        $query = Allocation::query();

        $added = $this->subject->apply($query, self::COLUMNS, [[1, 2], [3, 4], [1, 2]]);

        $this->assertTrue($added);
        $this->assertSame(
            'select * from "allocations" where (("allocations"."booking_id", "allocations"."vehicle_id") IN ((?, ?), (?, ?)))',
            $query->toSql()
        );
        $this->assertSame([1, 2, 3, 4], $query->getBindings());
    }

    public function test_tuples_with_null_components_become_nested_groups()
    {
        $query = Allocation::query();

        $added = $this->subject->apply($query, self::COLUMNS, [[1, 2], [3, null], [null, 4]]);

        $this->assertTrue($added);
        $this->assertSame(
            'select * from "allocations" where (("allocations"."booking_id", "allocations"."vehicle_id") IN ((?, ?))'
            .' or ("allocations"."booking_id" = ? and "allocations"."vehicle_id" is null)'
            .' or ("allocations"."booking_id" is null and "allocations"."vehicle_id" = ?))',
            $query->toSql()
        );
        $this->assertSame([1, 2, 3, 4], $query->getBindings());
    }

    public function test_all_null_tuples_add_no_constraint()
    {
        $query = Allocation::query();

        $added = $this->subject->apply($query, self::COLUMNS, [[null, null], [null, null]]);

        $this->assertFalse($added);
        $this->assertSame('select * from "allocations"', $query->toSql());
    }

    public function test_enum_components_are_resolved_and_deduplicated()
    {
        $query = Allocation::query();

        $this->subject->apply($query, self::COLUMNS, [[PivotRole::Lead, 1], ['lead', '1']]);

        $this->assertSame(['lead', 1], $query->getBindings());
    }
}
