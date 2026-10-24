<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'sku' => strtoupper($this->faker->unique()->bothify('??-####')),
            'category' => $this->faker->randomElement(['Балони', 'Текстил', 'Декор', 'Консумативи']),
            'unit_id' => Unit::factory(),
            'default_unit_price' => $this->faker->randomFloat(2, 0.1, 50),
            'description' => null,
            'is_active' => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
