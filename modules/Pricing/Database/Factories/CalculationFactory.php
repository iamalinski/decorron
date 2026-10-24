<?php

declare(strict_types=1);

namespace Modules\Pricing\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Pricing\Models\Calculation;

/**
 * @extends Factory<Calculation>
 */
class CalculationFactory extends Factory
{
    protected $model = Calculation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'calculation_number' => 'K-'.Carbon::now()->year.'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'service_name' => $this->faker->randomElement(['Балонена арка', 'Фотозона', 'Украса за рожден ден']),
            'client_name' => $this->faker->optional()->name(),
            'notes' => null,
            'calculated_at' => Carbon::today()->toDateString(),
            'material_cost_total' => '40.00',
            'labor_cost' => '40.00',
            'other_costs' => '0.00',
            'total_cost' => '80.00',
            'target_net_margin' => '0.400000',
            'corporate_tax_rate_snapshot' => '0.100000',
            'dividend_tax_rate_snapshot' => '0.050000',
            'vat_rate_snapshot' => '0.000000',
            'profit_before_tax' => '70.33',
            'corporate_tax_amount' => '7.03',
            'profit_after_corporate_tax' => '63.30',
            'dividend_tax_amount' => '3.17',
            'net_profit' => '60.13',
            'final_price' => '150.33',
            'actual_net_margin' => '0.399987',
            'price_was_rounded' => false,
            'currency' => 'EUR',
        ];
    }
}
