<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Unit;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'symbol' => substr(str_replace(' ', '', $name), 0, 8).$this->faker->unique()->numberBetween(1, 9999),
            'description' => null,
            'is_active' => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
