<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Unit;
use Modules\Crm\Models\ActivityLog;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Pricing\Models\Calculation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalculationCrmLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->unit = Unit::factory()->for($this->user)->create(['name' => 'Брой', 'symbol' => 'бр.']);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'service_name' => 'Балонена арка',
            'calculated_at' => '2026-03-01',
            'labor_cost' => '40',
            'other_costs' => '0',
            'target_net_margin_percent' => 40,
            'items' => [[
                'material_name' => 'Балони',
                'unit_id' => $this->unit->getKey(),
                'quantity' => '10',
                'input_mode' => 'total',
                'entered_total_cost' => '40',
            ]],
        ], $overrides);
    }

    #[Test]
    public function a_calculation_without_a_customer_creates_nothing_in_the_crm(): void
    {
        $this->actingAs($this->user)
            ->post('/calculations', $this->payload())
            ->assertSessionHasNoErrors();

        $calculation = Calculation::query()->firstOrFail();

        $this->assertNull($calculation->customer_id);
        $this->assertNull($calculation->crm_opportunity_id);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('crm_opportunities', 0);
        $this->assertDatabaseCount('crm_activity_logs', 0);
    }

    #[Test]
    public function a_calculation_attaches_to_the_single_open_opportunity_of_the_customer(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        $opportunity = Opportunity::factory()->for($this->user)->for($customer)->create();

        $this->actingAs($this->user)
            ->post('/calculations', $this->payload(['customer_id' => $customer->getKey()]))
            ->assertSessionHasNoErrors();

        $calculation = Calculation::query()->firstOrFail();

        $this->assertSame($customer->getKey(), $calculation->customer_id);
        $this->assertSame($opportunity->getKey(), $calculation->crm_opportunity_id);
        // Името на клиента се записва като snapshot.
        $this->assertSame($customer->name, $calculation->client_name);

        $this->assertDatabaseHas('crm_activity_logs', [
            'opportunity_id' => $opportunity->getKey(),
            'type' => ActivityLog::TYPE_CALCULATION_LINKED,
            'is_automatic' => true,
        ]);

        // Не е създадена нова възможност.
        $this->assertDatabaseCount('crm_opportunities', 1);
    }

    #[Test]
    public function with_several_open_opportunities_the_system_does_not_guess(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        Opportunity::factory()->for($this->user)->for($customer)->count(2)->create();

        $this->actingAs($this->user)->post('/calculations', $this->payload(['customer_id' => $customer->getKey()]));

        $calculation = Calculation::query()->firstOrFail();

        $this->assertSame($customer->getKey(), $calculation->customer_id);
        $this->assertNull($calculation->crm_opportunity_id);
        $this->assertDatabaseCount('crm_opportunities', 2);
    }

    #[Test]
    public function a_closed_opportunity_is_not_used_automatically(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        Opportunity::factory()->for($this->user)->for($customer)->won()->create();

        $this->actingAs($this->user)->post('/calculations', $this->payload(['customer_id' => $customer->getKey()]));

        $this->assertNull(Calculation::query()->firstOrFail()->crm_opportunity_id);
    }

    #[Test]
    public function a_new_opportunity_is_created_only_when_requested(): void
    {
        $customer = Customer::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->post('/calculations', $this->payload([
                'customer_id' => $customer->getKey(),
                'create_opportunity' => true,
            ]))
            ->assertSessionHasNoErrors();

        $opportunity = Opportunity::query()->firstOrFail();
        $calculation = Calculation::query()->firstOrFail();

        $this->assertSame('Балонена арка', $opportunity->title);
        // Новата възможност започва от „Изготвяне на цена“ — не от „Ново запитване“
        // и не от „Консултация“, защото такива действия не са се случили.
        $this->assertSame(Opportunity::STAGE_PRICING, $opportunity->stage);
        $this->assertSame(Opportunity::STATUS_OPEN, $opportunity->status);
        $this->assertSame($opportunity->getKey(), $calculation->crm_opportunity_id);
    }

    #[Test]
    public function no_opportunity_is_created_when_the_user_did_not_ask_for_one(): void
    {
        $customer = Customer::factory()->for($this->user)->create();

        $this->actingAs($this->user)->post('/calculations', $this->payload(['customer_id' => $customer->getKey()]));

        $this->assertDatabaseCount('crm_opportunities', 0);
        $this->assertSame($customer->getKey(), Calculation::query()->firstOrFail()->customer_id);
    }

    #[Test]
    public function a_calculation_can_be_attached_to_a_chosen_opportunity(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        Opportunity::factory()->for($this->user)->for($customer)->create();
        $chosen = Opportunity::factory()->for($this->user)->for($customer)->create();

        $this->actingAs($this->user)
            ->post('/calculations', $this->payload([
                'customer_id' => $customer->getKey(),
                'opportunity_id' => $chosen->getKey(),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($chosen->getKey(), Calculation::query()->firstOrFail()->crm_opportunity_id);
    }

    #[Test]
    public function an_opportunity_of_another_customer_is_rejected(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        $foreign = Opportunity::factory()->for($this->user)->for(Customer::factory()->for($this->user))->create();

        $this->actingAs($this->user)
            ->post('/calculations', $this->payload([
                'customer_id' => $customer->getKey(),
                'opportunity_id' => $foreign->getKey(),
            ]))
            ->assertSessionHasErrors('opportunity_id');

        $this->assertDatabaseCount('calculations', 0);
    }

    #[Test]
    public function an_opportunity_of_another_user_is_rejected(): void
    {
        $other = User::factory()->create();
        $foreign = Opportunity::factory()->for($other)->for(Customer::factory()->for($other))->create();
        $customer = Customer::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->post('/calculations', $this->payload([
                'customer_id' => $customer->getKey(),
                'opportunity_id' => $foreign->getKey(),
            ]))
            ->assertSessionHasErrors('opportunity_id');
    }

    #[Test]
    public function linking_to_crm_requires_a_customer(): void
    {
        $this->actingAs($this->user)
            ->post('/calculations', $this->payload(['create_opportunity' => true]))
            ->assertSessionHasErrors('customer_id');
    }

    #[Test]
    public function repeating_the_same_submit_does_not_create_a_second_opportunity(): void
    {
        $customer = Customer::factory()->for($this->user)->create();

        $payload = $this->payload([
            'customer_id' => $customer->getKey(),
            'create_opportunity' => true,
            'idempotency_key' => 'repeat-1',
        ]);

        $this->actingAs($this->user)->post('/calculations', $payload);
        $this->actingAs($this->user)->post('/calculations', $payload);

        $this->assertDatabaseCount('calculations', 1);
        $this->assertDatabaseCount('crm_opportunities', 1);
    }

    #[Test]
    public function recalculating_for_the_same_opportunity_does_not_create_another_one(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        $opportunity = Opportunity::factory()->for($this->user)->for($customer)->create();

        foreach (['50', '60'] as $labor) {
            $this->actingAs($this->user)->post('/calculations', $this->payload([
                'customer_id' => $customer->getKey(),
                'opportunity_id' => $opportunity->getKey(),
                'labor_cost' => $labor,
                'idempotency_key' => 'calc-'.$labor,
            ]))->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('calculations', 2);
        $this->assertDatabaseCount('crm_opportunities', 1);
        $this->assertSame(2, Calculation::query()->where('crm_opportunity_id', $opportunity->getKey())->count());
    }

    #[Test]
    public function linking_a_calculation_does_not_move_the_opportunity_stage(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        $opportunity = Opportunity::factory()->for($this->user)->for($customer)->create(['stage' => Opportunity::STAGE_NEW]);

        $this->actingAs($this->user)->post('/calculations', $this->payload([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
        ]));

        // Етапът се мени само когато потребителят го поиска.
        $this->assertSame(Opportunity::STAGE_NEW, $opportunity->fresh()->stage);
    }

    #[Test]
    public function a_calculation_never_records_a_consultation_as_held(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        $opportunity = Opportunity::factory()->for($this->user)->for($customer)->create();

        $this->actingAs($this->user)->post('/calculations', $this->payload([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
        ]));

        $types = ActivityLog::query()->pluck('type')->all();

        $this->assertSame([ActivityLog::TYPE_CALCULATION_LINKED], $types);
        $this->assertSame(Opportunity::STATUS_OPEN, $opportunity->fresh()->status);
    }

    #[Test]
    public function another_users_customer_cannot_be_linked(): void
    {
        $foreign = Customer::factory()->for(User::factory())->create();

        $this->actingAs($this->user)
            ->post('/calculations', $this->payload(['customer_id' => $foreign->getKey()]))
            ->assertSessionHasErrors('customer_id');
    }

    #[Test]
    public function the_opportunity_page_lists_the_linked_calculations(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        $opportunity = Opportunity::factory()->for($this->user)->for($customer)->create();

        $this->actingAs($this->user)->post('/calculations', $this->payload([
            'customer_id' => $customer->getKey(),
            'opportunity_id' => $opportunity->getKey(),
        ]));

        $this->actingAs($this->user)
            ->get(route('crm.opportunities.show', $opportunity))
            ->assertInertia(fn ($page) => $page
                ->has('calculations.data', 1)
                ->where('calculations.data.0.service_name', 'Балонена арка'));
    }

    #[Test]
    public function the_new_calculation_screen_can_be_opened_from_an_opportunity(): void
    {
        $customer = Customer::factory()->for($this->user)->create();
        $opportunity = Opportunity::factory()->for($this->user)->for($customer)->create();

        $this->actingAs($this->user)
            ->get(route('calculations.create', ['opportunity' => $opportunity->getKey()]))
            ->assertInertia(fn ($page) => $page
                ->component('pricing::calculations/create')
                ->where('presetOpportunity.data.id', $opportunity->getKey())
                ->where('presetCustomer.data.id', $customer->getKey()));
    }
}
