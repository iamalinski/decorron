<?php

declare(strict_types=1);

namespace Modules\Crm\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Support\CrmOptions;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_id' => Customer::factory(),
            'title' => 'Украса за '.$this->faker->randomElement(CrmOptions::EVENT_TYPE_SUGGESTIONS),
            'event_type' => $this->faker->randomElement(CrmOptions::EVENT_TYPE_SUGGESTIONS),
            'event_date' => Carbon::today()->addDays($this->faker->numberBetween(5, 90))->toDateString(),
            'event_location' => $this->faker->city(),
            'expected_budget' => null,
            'source' => $this->faker->randomElement(CrmOptions::sourceValues()),
            'stage' => Opportunity::STAGE_NEW,
            'status' => Opportunity::STATUS_OPEN,
            'notes' => null,
        ];
    }

    public function won(): static
    {
        return $this->state(fn (): array => [
            'status' => Opportunity::STATUS_WON,
            'closed_at' => Carbon::now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (): array => [
            'status' => Opportunity::STATUS_LOST,
            'lost_reason' => 'price',
            'closed_at' => Carbon::now(),
        ]);
    }
}
