<?php

namespace Awobaz\Compoships\Tests\Factories;

use Awobaz\Compoships\Database\Eloquent\Factories\ComposhipsFactory;
use Awobaz\Compoships\Tests\Models\Allocation;
use Awobaz\Compoships\Tests\Models\OriginalPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

class OriginalPackageFactory extends Factory
{
    use ComposhipsFactory;

    protected $model = OriginalPackage::class;

    public function definition(): array
    {
        return [
            'allocation_id' => Allocation::factory(),
        ];
    }
}