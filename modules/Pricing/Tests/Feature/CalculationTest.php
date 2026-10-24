<?php

declare(strict_types=1);

namespace Modules\Pricing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;
use Modules\Core\Models\UserSetting;
use Modules\Pricing\Models\Calculation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->unit = Unit::factory()->create(['user_id' => $this->user->id, 'name' => 'Брой', 'symbol' => 'бр.']);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'service_name' => 'Балонена арка',
            'client_name' => 'Мария',
            'notes' => null,
            'calculated_at' => Carbon::today()->toDateString(),
            'labor_cost' => '40',
            'other_costs' => '0',
            'target_net_margin_percent' => '40',
            'items' => [
                [
                    'material_id' => null,
                    'material_name' => 'Балони хром',
                    'sku' => null,
                    'unit_id' => $this->unit->id,
                    'quantity' => '10',
                    'input_mode' => 'total',
                    'entered_total_cost' => '10',
                    'unit_price' => null,
                ],
                [
                    'material_id' => null,
                    'material_name' => 'Балони пастел',
                    'sku' => null,
                    'unit_id' => $this->unit->id,
                    'quantity' => '20',
                    'input_mode' => 'total',
                    'entered_total_cost' => '30',
                    'unit_price' => null,
                ],
            ],
        ], $overrides);
    }

    #[Test]
    public function guests_cannot_reach_the_calculation_screens(): void
    {
        $this->get(route('calculations.create'))->assertRedirect(route('login'));
        $this->get(route('calculations.index'))->assertRedirect(route('login'));
        $this->post(route('calculations.store'), [])->assertRedirect(route('login'));
    }

    #[Test]
    public function it_stores_a_calculation_with_server_computed_totals(): void
    {
        $response = $this->actingAs($this->user)->post(route('calculations.store'), $this->payload());

        $calculation = Calculation::query()->firstOrFail();

        $response->assertRedirect(route('calculations.show', $calculation));

        $this->assertSame('40.00', $calculation->material_cost_total);
        $this->assertSame('80.00', $calculation->total_cost);
        $this->assertSame('150.33', $calculation->final_price);
        $this->assertSame('60.13', $calculation->net_profit);
        $this->assertSame('0.400000', $calculation->target_net_margin);
        $this->assertSame('0.100000', $calculation->corporate_tax_rate_snapshot);
        $this->assertSame('0.050000', $calculation->dividend_tax_rate_snapshot);
        $this->assertSame('0.000000', $calculation->vat_rate_snapshot);
        $this->assertSame('EUR', $calculation->currency);
        $this->assertCount(2, $calculation->items);
        $this->assertSame('1.000000', $calculation->items[0]->unit_price);
        $this->assertSame('K-'.Carbon::today()->year.'-0001', $calculation->calculation_number);
    }

    /** Клиентът не може да наложи собствена крайна цена или печалба. */
    #[Test]
    public function it_ignores_financial_values_sent_by_the_client(): void
    {
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload([
            'final_price' => '5.00',
            'net_profit' => '9999.00',
            'corporate_tax_amount' => '0',
            'corporate_tax_rate_snapshot' => '0.5',
        ]));

        $calculation = Calculation::query()->firstOrFail();

        $this->assertSame('150.33', $calculation->final_price);
        $this->assertSame('60.13', $calculation->net_profit);
        $this->assertSame('0.100000', $calculation->corporate_tax_rate_snapshot);
    }

    #[Test]
    public function a_rounded_price_is_recalculated_and_flagged(): void
    {
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload([
            'final_price_override' => '155',
        ]));

        $calculation = Calculation::query()->firstOrFail();

        $this->assertTrue($calculation->price_was_rounded);
        $this->assertSame('155.00', $calculation->final_price);
        $this->assertSame('75.00', $calculation->profit_before_tax);
        $this->assertSame('64.12', $calculation->net_profit);
    }

    /** Сценарий D: промяна на настройката не пипа историята. */
    #[Test]
    public function changing_the_default_margin_does_not_touch_saved_calculations(): void
    {
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload(['target_net_margin_percent' => '40']));
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload([
            'service_name' => 'Фотозона',
            'target_net_margin_percent' => '30',
        ]));

        $this->actingAs($this->user)
            ->patch(route('settings.pricing.update'), [
                'default_net_margin_percent' => '55',
                'default_rounding_strategy' => 'integer',
            ])
            ->assertRedirect();

        $first = Calculation::query()->where('service_name', 'Балонена арка')->firstOrFail();
        $second = Calculation::query()->where('service_name', 'Фотозона')->firstOrFail();

        $this->assertSame('0.400000', $first->target_net_margin);
        $this->assertSame('150.33', $first->final_price);
        $this->assertSame('0.300000', $second->target_net_margin);
        $this->assertSame('0.550000', UserSetting::forUser($this->user)->refresh()->default_net_margin);
    }

    /** Сценарий E: промяна на цена в каталога не пипа историята. */
    #[Test]
    public function changing_a_material_price_does_not_touch_saved_calculations(): void
    {
        $material = Material::factory()->create([
            'user_id' => $this->user->id,
            'unit_id' => $this->unit->id,
            'name' => 'Балони хром',
            'default_unit_price' => '1.000000',
        ]);

        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload([
            'labor_cost' => '0',
            'items' => [[
                'material_id' => $material->id,
                'material_name' => $material->name,
                'sku' => $material->sku,
                'unit_id' => $this->unit->id,
                'quantity' => '10',
                'input_mode' => 'unit',
                'entered_total_cost' => null,
                'unit_price' => '1',
            ]],
        ]));

        $calculation = Calculation::query()->firstOrFail();

        $this->actingAs($this->user)->patch(route('materials.update', $material), [
            'name' => $material->name,
            'sku' => $material->sku,
            'category' => $material->category,
            'unit_id' => $this->unit->id,
            'default_unit_price' => '2',
            'description' => null,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('2.000000', $material->refresh()->default_unit_price);

        $item = $calculation->items()->firstOrFail();
        $this->assertSame('1.000000', $item->unit_price);
        $this->assertSame('10.00', $item->line_total);
        $this->assertSame('Балони хром', $item->material_name_snapshot);
        $this->assertSame('10.00', $calculation->refresh()->material_cost_total);
    }

    /** Архивиран материал не поврежда стара калкулация. */
    #[Test]
    public function archiving_a_material_keeps_history_readable(): void
    {
        $material = Material::factory()->create(['user_id' => $this->user->id, 'unit_id' => $this->unit->id]);

        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload([
            'items' => [[
                'material_id' => $material->id,
                'material_name' => $material->name,
                'sku' => $material->sku,
                'unit_id' => $this->unit->id,
                'quantity' => '2',
                'input_mode' => 'unit',
                'entered_total_cost' => null,
                'unit_price' => '3',
            ]],
        ]));

        $this->actingAs($this->user)->patch(route('materials.archive', $material))->assertRedirect();
        $this->assertFalse($material->refresh()->is_active);

        $calculation = Calculation::query()->firstOrFail();
        $this->assertSame($material->name, $calculation->items()->firstOrFail()->material_name_snapshot);

        $this->actingAs($this->user)->get(route('calculations.show', $calculation))->assertOk();
    }

    #[Test]
    public function duplicating_prefills_a_new_calculation_without_changing_the_original(): void
    {
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload());
        $original = Calculation::query()->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('calculations.duplicate', $original))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pricing::calculations/create')
                ->where('duplicateOf.data.service_name', 'Балонена арка'));

        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload(['service_name' => 'Балонена арка (копие)']));

        $this->assertSame(2, Calculation::query()->count());
        $this->assertSame('150.33', $original->refresh()->final_price);
        $this->assertNotSame(
            $original->calculation_number,
            Calculation::query()->where('service_name', 'Балонена арка (копие)')->value('calculation_number'),
        );
    }

    #[Test]
    public function a_repeated_submit_with_the_same_key_does_not_create_a_duplicate(): void
    {
        $payload = $this->payload(['idempotency_key' => 'fixed-key-123']);

        $this->actingAs($this->user)->post(route('calculations.store'), $payload);
        $second = $this->actingAs($this->user)->post(route('calculations.store'), $payload);

        $this->assertSame(1, Calculation::query()->count());
        $second->assertRedirect(route('calculations.show', Calculation::query()->value('id')));
    }

    #[Test]
    public function the_history_can_be_searched_and_paginated(): void
    {
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload(['service_name' => 'Балонена арка']));
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload(['service_name' => 'Фотозона']));

        $this->actingAs($this->user)
            ->get(route('calculations.index', ['search' => 'Фотозона']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pricing::calculations/index')
                ->has('calculations.data', 1)
                ->where('calculations.data.0.service_name', 'Фотозона'));
    }

    #[Test]
    public function a_user_cannot_see_or_delete_another_users_calculation(): void
    {
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload());
        $calculation = Calculation::query()->firstOrFail();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('calculations.show', $calculation))->assertForbidden();
        $this->actingAs($intruder)->delete(route('calculations.destroy', $calculation))->assertForbidden();
        $this->actingAs($intruder)->get(route('calculations.index'))
            ->assertInertia(fn ($page) => $page->has('calculations.data', 0));

        $this->assertDatabaseHas('calculations', ['id' => $calculation->id]);
    }

    #[Test]
    public function a_calculation_can_be_deleted_with_its_items(): void
    {
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload());
        $calculation = Calculation::query()->firstOrFail();

        $this->actingAs($this->user)
            ->delete(route('calculations.destroy', $calculation))
            ->assertRedirect(route('calculations.index'));

        $this->assertDatabaseMissing('calculations', ['id' => $calculation->id]);
        $this->assertDatabaseMissing('calculation_items', ['calculation_id' => $calculation->id]);
    }

    #[Test]
    public function the_print_view_is_available(): void
    {
        $this->actingAs($this->user)->post(route('calculations.store'), $this->payload());

        $this->actingAs($this->user)
            ->get(route('calculations.print', Calculation::query()->value('id')))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('pricing::calculations/print'));
    }
}
