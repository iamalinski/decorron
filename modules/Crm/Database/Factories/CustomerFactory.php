<?php

declare(strict_types=1);

namespace Modules\Crm\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Crm\Models\Customer;
use Modules\Crm\Support\CrmOptions;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'individual',
            'name' => $this->faker->name(),
            'contact_person' => null,
            'phone' => '08'.$this->faker->numerify('########'),
            'email' => $this->faker->unique()->safeEmail(),
            'city' => $this->faker->city(),
            'address' => null,
            'source' => $this->faker->randomElement(CrmOptions::sourceValues()),
            'notes' => null,
            'is_archived' => false,
        ];
    }

    public function organization(): static
    {
        return $this->state(fn (): array => [
            'type' => 'organization',
            'name' => $this->faker->company(),
            'contact_person' => $this->faker->name(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['is_archived' => true]);
    }
}
