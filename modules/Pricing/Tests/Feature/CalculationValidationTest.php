<?php

declare(strict_types=1);

namespace Modules\Pricing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\Unit;
use Modules\Pricing\Models\Calculation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Сценарий F: валидация на входните данни. */
class CalculationValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->unit = Unit::factory()->create(['user_id' => $this->user->id]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = [], array $itemOverrides = []): array
    {
        return array_merge([
            'service_name' => 'Балонена арка',
            'calculated_at' => Carbon::today()->toDateString(),
            'labor_cost' => '10',
            'other_costs' => '0',
            'target_net_margin_percent' => '40',
            'items' => [array_merge([
                'material_id' => null,
                'material_name' => 'Балони',
                'sku' => null,
                'unit_id' => $this->unit->id,
                'quantity' => '10',
                'input_mode' => 'total',
                'entered_total_cost' => '10',
                'unit_price' => null,
            ], $itemOverrides)],
        ], $overrides);
    }

    #[Test]
    public function the_service_name_is_required(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['service_name' => '']))
            ->assertSessionHasErrors('service_name');

        $this->assertSame(0, Calculation::query()->count());
    }

    #[Test]
    public function a_zero_quantity_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload([], ['quantity' => '0']))
            ->assertSessionHasErrors('items.0.quantity');
    }

    #[Test]
    public function a_negative_quantity_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload([], ['quantity' => '-5']))
            ->assertSessionHasErrors('items.0.quantity');
    }

    #[Test]
    public function a_negative_price_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload([], ['entered_total_cost' => '-10']))
            ->assertSessionHasErrors('items.0.entered_total_cost');
    }

    #[Test]
    public function a_negative_labor_cost_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['labor_cost' => '-1']))
            ->assertSessionHasErrors('labor_cost');
    }

    /** Марж, равен или над net_factor (85,5%), прави цената безкрайна. */
    #[Test]
    public function an_impossible_margin_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['target_net_margin_percent' => '85.5']))
            ->assertSessionHasErrors('target_net_margin_percent');

        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['target_net_margin_percent' => '120']))
            ->assertSessionHasErrors('target_net_margin_percent');
    }

    #[Test]
    public function a_negative_margin_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['target_net_margin_percent' => '-5']))
            ->assertSessionHasErrors('target_net_margin_percent');
    }

    #[Test]
    public function a_margin_just_below_the_limit_is_accepted(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['target_net_margin_percent' => '85']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Calculation::query()->count());
    }

    #[Test]
    public function an_unknown_unit_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload([], ['unit_id' => 999999]))
            ->assertSessionHasErrors('items.0.unit_id');
    }

    #[Test]
    public function a_unit_belonging_to_another_user_is_rejected(): void
    {
        $other = Unit::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload([], ['unit_id' => $other->id]))
            ->assertSessionHasErrors('items.0.unit_id');
    }

    #[Test]
    public function a_calculation_without_any_cost_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['labor_cost' => '0', 'other_costs' => '0', 'items' => []]))
            ->assertSessionHasErrors('total_cost');
    }

    #[Test]
    public function a_calculation_without_materials_but_with_labor_is_accepted(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['labor_cost' => '120', 'items' => []]))
            ->assertSessionHasNoErrors();

        $calculation = Calculation::query()->firstOrFail();

        $this->assertSame('120.00', $calculation->total_cost);
        $this->assertSame('225.49', $calculation->final_price);
    }

    #[Test]
    public function a_client_and_notes_are_optional(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['client_name' => null, 'notes' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull(Calculation::query()->value('client_name'));
    }

    #[Test]
    public function a_rounded_price_below_cost_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('calculations.store'), $this->payload(['final_price_override' => '1']))
            ->assertSessionHasErrors('final_price');
    }
}
