<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Crm\Models\ActivityLog;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Order;
use Modules\Crm\Models\Quote;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->for($this->user)->create([
            'name' => 'Мария Иванова',
            'phone' => '0888123456',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->getKey(),
            'issued_at' => '2026-03-01',
            'valid_until' => '2026-03-15',
            'items' => [
                ['description' => 'Балонена арка', 'quantity' => '1', 'unit_price' => '150.33'],
                ['description' => 'Фотозона', 'quantity' => '2', 'unit_price' => '80.00'],
            ],
        ], $overrides);
    }

    private function createQuote(array $overrides = []): Quote
    {
        $this->actingAs($this->user)->post('/crm/quotes', $this->payload($overrides))->assertSessionHasNoErrors();

        return Quote::query()->latest('id')->firstOrFail();
    }

    #[Test]
    public function guests_cannot_reach_quotes(): void
    {
        $this->get('/crm/quotes')->assertRedirect('/login');
    }

    #[Test]
    public function a_quote_is_created_as_a_draft_with_server_computed_totals(): void
    {
        $quote = $this->createQuote();

        $this->assertSame(Quote::STATUS_DRAFT, $quote->status);
        $this->assertSame(1, (int) $quote->version);
        $this->assertSame('OF-2026-0001', $quote->quote_number);
        // 150.33 + 2 × 80.00
        $this->assertSame('310.33', (string) $quote->subtotal);
        $this->assertSame('310.33', (string) $quote->total);
        $this->assertCount(2, $quote->items);
        // Данните на клиента са snapshot.
        $this->assertSame('Мария Иванова', $quote->customer_name_snapshot);
        $this->assertSame('0888123456', $quote->customer_phone_snapshot);
    }

    #[Test]
    public function totals_sent_by_the_client_are_ignored(): void
    {
        $quote = $this->createQuote(['subtotal' => '1', 'total' => '1', 'discount_amount' => '999']);

        $this->assertSame('310.33', (string) $quote->total);
        $this->assertSame('0.00', (string) $quote->discount_amount);
    }

    #[Test]
    public function a_percentage_discount_is_applied(): void
    {
        $quote = $this->createQuote(['discount_type' => 'percent', 'discount_value' => '10']);

        $this->assertSame('31.03', (string) $quote->discount_amount);
        $this->assertSame('279.30', (string) $quote->total);
    }

    #[Test]
    public function a_discount_cannot_exceed_the_subtotal(): void
    {
        $quote = $this->createQuote(['discount_type' => 'amount', 'discount_value' => '5000']);

        $this->assertSame('310.33', (string) $quote->discount_amount);
        $this->assertSame('0.00', (string) $quote->total);
    }

    #[Test]
    public function a_percentage_discount_above_one_hundred_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post('/crm/quotes', $this->payload(['discount_type' => 'percent', 'discount_value' => '150']))
            ->assertSessionHasErrors('discount_value');
    }

    #[Test]
    public function a_quote_needs_at_least_one_item(): void
    {
        $this->actingAs($this->user)
            ->post('/crm/quotes', $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');
    }

    #[Test]
    public function validity_cannot_be_before_the_issue_date(): void
    {
        $this->actingAs($this->user)
            ->post('/crm/quotes', $this->payload(['valid_until' => '2026-02-01']))
            ->assertSessionHasErrors('valid_until');
    }

    #[Test]
    public function another_users_customer_is_rejected(): void
    {
        $foreign = Customer::factory()->for(User::factory())->create();

        $this->actingAs($this->user)
            ->post('/crm/quotes', $this->payload(['customer_id' => $foreign->getKey()]))
            ->assertSessionHasErrors('customer_id');
    }

    #[Test]
    public function printing_does_not_mark_the_quote_as_sent(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->user)
            ->get(route('crm.quotes.print', $quote))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('crm::quotes/print'));

        $fresh = $quote->fresh();

        $this->assertSame(Quote::STATUS_DRAFT, $fresh->status);
        $this->assertNull($fresh->sent_at);
        $this->assertDatabaseCount('quote_versions', 0);
    }

    #[Test]
    public function the_printable_quote_never_exposes_internal_costs(): void
    {
        $opportunity = Opportunity::factory()->for($this->user)->for($this->customer)->create();
        $quote = $this->createQuote(['crm_opportunity_id' => $opportunity->getKey(), 'internal_notes' => 'Себестойност 120 EUR']);

        $response = $this->actingAs($this->user)->get(route('crm.quotes.print', $quote));

        $props = $response->viewData('page')['props']['quote']['data'];

        foreach (['total_cost', 'net_profit', 'target_net_margin', 'material_cost_total', 'internal_notes'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $props, "Клиентският изглед не трябва да съдържа {$forbidden}.");
        }
    }

    #[Test]
    public function marking_as_sent_freezes_a_version_and_logs_it(): void
    {
        $opportunity = Opportunity::factory()->for($this->user)->for($this->customer)->create();
        $quote = $this->createQuote(['crm_opportunity_id' => $opportunity->getKey()]);

        $this->actingAs($this->user)
            ->patch(route('crm.quotes.status', $quote), ['status' => 'sent'])
            ->assertSessionHasNoErrors();

        $fresh = $quote->fresh();

        $this->assertSame(Quote::STATUS_SENT, $fresh->status);
        $this->assertNotNull($fresh->sent_at);

        $this->assertDatabaseHas('quote_versions', ['quote_id' => $quote->getKey(), 'version' => 1, 'total' => '310.33']);

        // Изпращането е ръчно действие — системата не го твърди сама.
        $this->assertDatabaseHas('crm_activity_logs', [
            'type' => ActivityLog::TYPE_QUOTE_SENT,
            'is_automatic' => false,
        ]);
    }

    #[Test]
    public function editing_a_sent_quote_creates_a_new_version_and_keeps_the_old_one(): void
    {
        $quote = $this->createQuote();
        $this->actingAs($this->user)->patch(route('crm.quotes.status', $quote), ['status' => 'sent']);

        $this->actingAs($this->user)->patch(route('crm.quotes.update', $quote), $this->payload([
            'items' => [['description' => 'Балонена арка', 'quantity' => '1', 'unit_price' => '200.00']],
        ]))->assertSessionHasNoErrors();

        $fresh = $quote->fresh();

        $this->assertSame(2, (int) $fresh->version);
        $this->assertSame(Quote::STATUS_DRAFT, $fresh->status);
        $this->assertSame('200.00', (string) $fresh->total);

        // Изпратената версия 1 остава непроменена.
        $this->assertDatabaseHas('quote_versions', ['quote_id' => $quote->getKey(), 'version' => 1, 'total' => '310.33']);
        $this->assertDatabaseMissing('quote_versions', ['quote_id' => $quote->getKey(), 'version' => 2]);
    }

    #[Test]
    public function an_accepted_quote_cannot_be_edited(): void
    {
        $quote = $this->createQuote();
        $this->actingAs($this->user)->patch(route('crm.quotes.status', $quote), ['status' => 'sent']);
        $this->actingAs($this->user)->patch(route('crm.quotes.status', $quote), ['status' => 'accepted']);

        $this->actingAs($this->user)
            ->patch(route('crm.quotes.update', $quote), $this->payload())
            ->assertSessionHasErrors('status');

        $this->assertSame('310.33', (string) $quote->fresh()->total);
    }

    #[Test]
    public function a_reply_cannot_be_recorded_before_the_quote_was_sent(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->user)
            ->patch(route('crm.quotes.status', $quote), ['status' => 'accepted'])
            ->assertSessionHasErrors('status');

        $this->assertSame(Quote::STATUS_DRAFT, $quote->fresh()->status);
    }

    #[Test]
    public function a_sent_quote_goes_to_the_trash_and_can_be_restored(): void
    {
        $quote = $this->createQuote();
        $this->actingAs($this->user)->patch(route('crm.quotes.status', $quote), ['status' => 'sent']);

        $this->actingAs($this->user)
            ->delete(route('crm.quotes.destroy', $quote))
            ->assertSessionHasNoErrors();

        // Записът остава в базата заедно със замразените си версии.
        $this->assertSoftDeleted('quotes', ['id' => $quote->getKey()]);
        $this->assertDatabaseHas('quote_versions', ['quote_id' => $quote->getKey()]);

        $this->actingAs($this->user)
            ->patch(route('crm.quotes.restore', $quote))
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('quotes', ['id' => $quote->getKey()]);
    }

    #[Test]
    public function a_draft_quote_is_moved_to_the_trash_and_can_be_purged(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->user)->delete(route('crm.quotes.destroy', $quote));

        $this->assertSoftDeleted('quotes', ['id' => $quote->getKey()]);
        // Редовете остават, докато офертата е в Кошчето.
        $this->assertDatabaseCount('quote_items', 2);

        $this->actingAs($this->user)
            ->delete(route('crm.quotes.force-destroy', $quote))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('quotes', 0);
        $this->assertDatabaseCount('quote_items', 0);
    }

    #[Test]
    public function a_quote_with_an_order_cannot_be_deleted_before_the_order(): void
    {
        $quote = $this->createQuote();
        $this->actingAs($this->user)->patch(route('crm.quotes.status', $quote), ['status' => 'sent']);
        $this->actingAs($this->user)->patch(route('crm.quotes.status', $quote), ['status' => 'accepted']);
        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote));

        $order = Order::query()->firstOrFail();

        $this->actingAs($this->user)
            ->delete(route('crm.quotes.destroy', $quote))
            ->assertSessionHasErrors('delete');

        $this->assertNotSoftDeleted('quotes', ['id' => $quote->getKey()]);

        // Веригата: първо поръчката, после офертата.
        $this->actingAs($this->user)->delete(route('crm.orders.destroy', $order))->assertSessionHasNoErrors();
        $this->actingAs($this->user)->delete(route('crm.quotes.destroy', $quote))->assertSessionHasNoErrors();

        $this->assertSoftDeleted('quotes', ['id' => $quote->getKey()]);
    }

    #[Test]
    public function a_trashed_quote_is_hidden_from_the_list_but_visible_in_the_trash(): void
    {
        $quote = $this->createQuote();
        $this->actingAs($this->user)->delete(route('crm.quotes.destroy', $quote));

        $this->actingAs($this->user)
            ->get('/crm/quotes')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('quotes.data', 0));

        $this->actingAs($this->user)
            ->get('/crm/quotes?status=deleted')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('quotes.data', 1));
    }

    #[Test]
    public function an_opportunity_of_another_customer_is_rejected(): void
    {
        $foreign = Opportunity::factory()->for($this->user)->for(Customer::factory()->for($this->user))->create();

        $this->actingAs($this->user)
            ->post('/crm/quotes', $this->payload(['crm_opportunity_id' => $foreign->getKey()]))
            ->assertSessionHasErrors('crm_opportunity_id');
    }

    #[Test]
    public function another_users_quote_is_not_accessible(): void
    {
        $quote = $this->createQuote();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('crm.quotes.show', $quote))->assertForbidden();
        $this->actingAs($intruder)->patch(route('crm.quotes.status', $quote), ['status' => 'sent'])->assertForbidden();
        $this->actingAs($intruder)->delete(route('crm.quotes.destroy', $quote))->assertForbidden();
    }

    #[Test]
    public function quote_numbers_are_sequential_per_user(): void
    {
        $this->createQuote();
        $second = $this->createQuote();

        $this->assertSame('OF-2026-0002', $second->quote_number);
    }

    #[Test]
    public function the_quote_form_can_be_prefilled_from_an_opportunity(): void
    {
        $opportunity = Opportunity::factory()->for($this->user)->for($this->customer)->create();

        $this->actingAs($this->user)
            ->get(route('crm.quotes.create', ['opportunity' => $opportunity->getKey()]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::quotes/form')
                ->where('presetOpportunity.data.id', $opportunity->getKey())
                ->where('presetCustomer.data.id', $this->customer->getKey()));
    }

    #[Test]
    public function the_company_details_appear_on_the_printable_quote(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->user)->patch(route('settings.company.update'), [
            'company_name' => 'Decorron Events',
            'company_phone' => '0899000111',
            'default_quote_validity_days' => 10,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->get(route('crm.quotes.print', $quote))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('company.name', 'Decorron Events')
                ->where('company.phone', '0899000111'));
    }
}
