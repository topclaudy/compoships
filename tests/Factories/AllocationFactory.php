<?php

namespace Awobaz\Compoships\Tests\Factories;

use Awobaz\Compoships\Tests\Model\Allocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class AllocationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Allocation::class;

    public function definition()
    {
        return [
            'booking_id' => rand(1, 100),
            'vehicle_id' => rand(1, 100),
        ];
    }
}
