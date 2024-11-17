<?php

namespace Awobaz\Compoships\Tests\Unit;

use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\OriginalPackage;
use Awobaz\Compoships\Tests\Models\ProductCode;
use Awobaz\Compoships\Tests\Models\User;
use Awobaz\Compoships\Tests\TestCase\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;

class LimitTest extends TestCase
{
    /**
     * @covers \Awobaz\Compoships\Database\Grammar\MySqlGrammar
     */
    public function test_relation_limit()
    {
        Model::unguard();

        $user = User::create([
            'booking_id' => '123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $user->allocations()->create([
                'booking_id' => '123',
            ]);
        }

        $allocations = $user->allocations()->get();
        $this->assertCount(5, $allocations);

        $user = $user->fresh();
        $user->load([
            'allocations' => fn ($query) => $query->limit(4),
        ]);
        $this->assertCount(4, $user->allocations);

        $user = $user->fresh();
        $user->load([
            'allocations' => fn ($query) => $query->limit(2),
        ]);
        $this->assertCount(2, $user->allocations);
    }
}
