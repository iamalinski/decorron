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

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->for($this->user)->create(['name' => 'Мария Иванова']);
    }

    private function acceptedQuote(?Opportunity $opportunity = null): Quote
    {
        $this->actingAs($this->user)->post('/crm/quotes', [
            'customer_id' => $this->customer->getKey(),
            'crm_opportunity_id' => $opportunity?->getKey(),
            'issued_at' => '2026-03-01',
            'items' => [
                ['description' => 'Балонена арка', 'quantity' => '1', 'unit_price' => '150.33'],
                ['description' => 'Фотозона', 'quantity' => '2', 'unit_price' => '80.00'],
            ],
        ])->assertSessionHasNoErrors();

        $quote = Quote::query()->latest('id')->firstOrFail();

        $this->actingAs($this->user)->patch(route('crm.quotes.status', $quote), ['status' => 'sent']);
        $this->actingAs($this->user)->patch(route('crm.quotes.status', $quote), ['status' => 'accepted']);

        return $quote->fresh();
    }

    #[Test]
    public function guests_cannot_reach_orders(): void
    {
        $this->get('/crm/orders')->assertRedirect('/login');
    }

    #[Test]
    public function an_accepted_quote_becomes_an_order(): void
    {
        $opportunity = Opportunity::factory()->for($this->user)->for($this->customer)->create();
        $quote = $this->acceptedQuote($opportunity);

        $this->actingAs($this->user)
            ->post(route('crm.quotes.order', $quote))
            ->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame($quote->getKey(), $order->quote_id);
        $this->assertSame(1, (int) $order->quote_version);
        $this->assertSame($opportunity->getKey(), $order->crm_opportunity_id);
        $this->assertSame('310.33', (string) $order->agreed_total);
        $this->assertSame('Мария Иванова', $order->customer_name_snapshot);
        $this->assertCount(2, $order->items);
        $this->assertDatabaseHas('crm_activity_logs', ['type' => ActivityLog::TYPE_ORDER_CREATED]);
    }

    #[Test]
    public function accepting_the_same_quote_twice_does_not_duplicate_the_order(): void
    {
        $quote = $this->acceptedQuote();

        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote));
        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
    }

    #[Test]
    public function a_quote_that_was_not_accepted_cannot_become_an_order(): void
    {
        $this->actingAs($this->user)->post('/crm/quotes', [
            'customer_id' => $this->customer->getKey(),
            'issued_at' => '2026-03-01',
            'items' => [['description' => 'Арка', 'quantity' => '1', 'unit_price' => '100']],
        ]);

        $quote = Quote::query()->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('crm.quotes.order', $quote))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('orders', 0);
    }

    #[Test]
    public function an_order_can_be_created_without_a_quote(): void
    {
        $this->actingAs($this->user)->post('/crm/orders', [
            'customer_id' => $this->customer->getKey(),
            'event_date' => '2026-06-20',
            'event_location' => 'София',
            'items' => [['description' => 'Украса за кръщене', 'quantity' => '1', 'unit_price' => '240.50']],
        ])->assertSessionHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertNull($order->quote_id);
        $this->assertSame('240.50', (string) $order->agreed_total);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame('P-2026-0001', $order->order_number);
    }

    #[Test]
    public function the_agreed_total_is_computed_on_the_server(): void
    {
        $this->actingAs($this->user)->post('/crm/orders', [
            'customer_id' => $this->customer->getKey(),
            'agreed_total' => '1',
            'items' => [['description' => 'Арка', 'quantity' => '3', 'unit_price' => '25.00']],
        ]);

        $this->assertSame('75.00', (string) Order::query()->firstOrFail()->agreed_total);
    }

    #[Test]
    public function an_order_needs_at_least_one_item(): void
    {
        $this->actingAs($this->user)
            ->post('/crm/orders', ['customer_id' => $this->customer->getKey(), 'items' => []])
            ->assertSessionHasErrors('items');
    }

    #[Test]
    public function another_users_customer_is_rejected(): void
    {
        $foreign = Customer::factory()->for(User::factory())->create();

        $this->actingAs($this->user)
            ->post('/crm/orders', [
                'customer_id' => $foreign->getKey(),
                'items' => [['description' => 'Арка', 'quantity' => '1', 'unit_price' => '10']],
            ])
            ->assertSessionHasErrors('customer_id');
    }

    #[Test]
    public function the_status_can_be_changed_and_timestamps_are_set(): void
    {
        $this->actingAs($this->user)->post('/crm/orders', [
            'customer_id' => $this->customer->getKey(),
            'items' => [['description' => 'Арка', 'quantity' => '1', 'unit_price' => '10']],
        ]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('crm.orders.status', $order), ['status' => 'completed'])
            ->assertSessionHasNoErrors();

        $fresh = $order->fresh();

        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    #[Test]
    public function an_unknown_status_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/crm/orders', [
            'customer_id' => $this->customer->getKey(),
            'items' => [['description' => 'Арка', 'quantity' => '1', 'unit_price' => '10']],
        ]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($this->user)
            ->patch(route('crm.orders.status', $order), ['status' => 'invented'])
            ->assertSessionHasErrors('status');
    }

    #[Test]
    public function the_order_keeps_the_link_to_quote_and_opportunity(): void
    {
        $opportunity = Opportunity::factory()->for($this->user)->for($this->customer)->create();
        $quote = $this->acceptedQuote($opportunity);

        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote));

        $order = Order::query()->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('crm.orders.show', $order))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::orders/show')
                ->where('order.data.quote_id', $quote->getKey())
                ->where('order.data.crm_opportunity_id', $opportunity->getKey())
                ->where('quote.data.quote_number', $quote->quote_number));
    }

    #[Test]
    public function editing_a_quote_after_an_order_does_not_change_the_order(): void
    {
        $quote = $this->acceptedQuote();
        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote));

        $order = Order::query()->firstOrFail();
        $totalBefore = (string) $order->agreed_total;

        // Приета оферта не се редактира, но дори да бъде отворена наново,
        // поръчката пази договорените стойности.
        $this->actingAs($this->user)->patch(route('crm.quotes.update', $quote), [
            'customer_id' => $this->customer->getKey(),
            'issued_at' => '2026-03-01',
            'items' => [['description' => 'Нещо друго', 'quantity' => '1', 'unit_price' => '999']],
        ]);

        $this->assertSame($totalBefore, (string) $order->fresh()->agreed_total);
    }

    #[Test]
    public function another_users_order_is_not_accessible(): void
    {
        $this->actingAs($this->user)->post('/crm/orders', [
            'customer_id' => $this->customer->getKey(),
            'items' => [['description' => 'Арка', 'quantity' => '1', 'unit_price' => '10']],
        ]);

        $order = Order::query()->firstOrFail();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('crm.orders.show', $order))->assertForbidden();
        $this->actingAs($intruder)->patch(route('crm.orders.status', $order), ['status' => 'completed'])->assertForbidden();
    }

    #[Test]
    public function the_list_shows_upcoming_orders_by_default(): void
    {
        foreach (['confirmed', 'completed'] as $status) {
            $this->actingAs($this->user)->post('/crm/orders', [
                'customer_id' => $this->customer->getKey(),
                'items' => [['description' => 'Арка '.$status, 'quantity' => '1', 'unit_price' => '10']],
            ]);

            $order = Order::query()->latest('id')->firstOrFail();
            $this->actingAs($this->user)->patch(route('crm.orders.status', $order), ['status' => $status]);
        }

        $this->actingAs($this->user)
            ->get('/crm/orders')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('orders.data', 1));

        $this->actingAs($this->user)
            ->get('/crm/orders?status=all')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('orders.data', 2));
    }

    #[Test]
    public function an_order_goes_to_the_trash_and_can_be_restored(): void
    {
        $quote = $this->acceptedQuote();
        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote));
        $order = Order::query()->firstOrFail();

        $this->actingAs($this->user)
            ->delete(route('crm.orders.destroy', $order))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('orders', ['id' => $order->getKey()]);

        $this->actingAs($this->user)
            ->get('/crm/orders?status=all')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('orders.data', 0));

        $this->actingAs($this->user)
            ->get('/crm/orders?status=deleted')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('orders.data', 1));

        $this->actingAs($this->user)->patch(route('crm.orders.restore', $order));

        $this->assertNotSoftDeleted('orders', ['id' => $order->getKey()]);
    }

    /**
     * Една оферта ражда най-много една поръчка, затова изтритата се връща,
     * вместо да се създаде втора и да се удари уникалният индекс.
     */
    #[Test]
    public function recreating_an_order_from_the_same_quote_restores_the_trashed_one(): void
    {
        $quote = $this->acceptedQuote();
        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote));
        $order = Order::query()->firstOrFail();

        $this->actingAs($this->user)->delete(route('crm.orders.destroy', $order));
        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 1);
        $this->assertNotSoftDeleted('orders', ['id' => $order->getKey()]);
    }

    #[Test]
    public function a_trashed_order_is_not_counted_as_committed_revenue(): void
    {
        $quote = $this->acceptedQuote();
        $this->actingAs($this->user)->post(route('crm.quotes.order', $quote));
        $order = Order::query()->firstOrFail();

        $this->actingAs($this->user)->delete(route('crm.orders.destroy', $order));

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.committed_upcoming_total', '0'));
    }
}
