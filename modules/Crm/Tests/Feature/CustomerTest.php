<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Catalog\Models\Unit;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    #[Test]
    public function guests_cannot_reach_the_crm(): void
    {
        $this->get('/crm/customers')->assertRedirect('/login');
        $this->post('/crm/customers', ['name' => 'Тест'])->assertRedirect('/login');
    }

    #[Test]
    public function a_customer_can_be_created(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->post('/crm/customers', [
            'type' => 'individual',
            'name' => 'Мария Иванова',
            'phone' => '0888 123 456',
            'email' => 'maria@example.com',
            'city' => 'София',
            'source' => 'instagram',
        ]);

        $customer = Customer::query()->firstOrFail();

        $response->assertRedirect(route('crm.customers.show', $customer));

        $this->assertSame('Мария Иванова', $customer->name);
        $this->assertSame($user->getKey(), $customer->user_id);
        $this->assertFalse($customer->is_archived);
        // Телефонът се нормализира автоматично за откриване на дубликати.
        $this->assertSame('888123456', $customer->phone_normalized);
    }

    #[Test]
    public function only_the_name_is_required(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->post('/crm/customers', ['type' => 'individual', 'name' => 'Само име'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customers', 1);
    }

    #[Test]
    public function a_customer_without_a_name_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->post('/crm/customers', ['type' => 'individual', 'name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('customers', 0);
    }

    #[Test]
    public function an_invalid_email_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->post('/crm/customers', ['type' => 'individual', 'name' => 'Тест', 'email' => 'не-е-имейл'])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function a_customer_can_be_edited(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create(['name' => 'Старо име']);

        $this->actingAs($user)
            ->patch(route('crm.customers.update', $customer), [
                'type' => $customer->type,
                'name' => 'Ново име',
                'phone' => $customer->phone,
                'email' => $customer->email,
                'confirm_duplicate' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ново име', $customer->fresh()->name);
    }

    #[Test]
    public function a_likely_duplicate_is_flagged_but_can_be_confirmed(): void
    {
        $user = $this->user();
        Customer::factory()->for($user)->create(['name' => 'Иван Петров', 'phone' => '0888111222']);

        // Същият телефон → предупреждение, без запис.
        $this->actingAs($user)
            ->post('/crm/customers', ['type' => 'individual', 'name' => 'И. Петров', 'phone' => '+359 888 111 222'])
            ->assertSessionHasErrors('duplicate');

        $this->assertDatabaseCount('customers', 1);

        // Потребителят потвърждава съзнателно → записва се.
        $this->actingAs($user)
            ->post('/crm/customers', [
                'type' => 'individual',
                'name' => 'И. Петров',
                'phone' => '+359 888 111 222',
                'confirm_duplicate' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customers', 2);
    }

    #[Test]
    public function a_duplicate_of_another_users_customer_is_not_flagged(): void
    {
        $other = $this->user();
        Customer::factory()->for($other)->create(['phone' => '0888111222']);

        $this->actingAs($this->user())
            ->post('/crm/customers', ['type' => 'individual', 'name' => 'Нов', 'phone' => '0888111222'])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_customer_can_be_archived_and_restored(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)->patch(route('crm.customers.archive', $customer));
        $this->assertTrue($customer->fresh()->is_archived);

        $this->actingAs($user)->patch(route('crm.customers.archive', $customer));
        $this->assertFalse($customer->fresh()->is_archived);
    }

    #[Test]
    public function the_list_only_shows_the_users_own_customers(): void
    {
        $user = $this->user();
        Customer::factory()->for($user)->create(['name' => 'Мой клиент']);
        Customer::factory()->for($this->user())->create(['name' => 'Чужд клиент']);

        $this->actingAs($user)
            ->get('/crm/customers')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::customers/index')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Мой клиент'));
    }

    #[Test]
    public function archived_customers_are_hidden_by_default(): void
    {
        $user = $this->user();
        Customer::factory()->for($user)->create(['name' => 'Активен']);
        Customer::factory()->for($user)->archived()->create(['name' => 'Архивиран']);

        $this->actingAs($user)
            ->get('/crm/customers')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('customers.data', 1));

        $this->actingAs($user)
            ->get('/crm/customers?status=archived')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Архивиран'));
    }

    #[Test]
    public function customers_can_be_searched_by_phone_in_any_format(): void
    {
        $user = $this->user();
        Customer::factory()->for($user)->create(['name' => 'Търсен', 'phone' => '0888 777 666']);
        Customer::factory()->for($user)->create(['name' => 'Друг', 'phone' => '0877 000 111']);

        $this->actingAs($user)
            ->get('/crm/customers?search=%2B359888777666')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Търсен'));
    }

    #[Test]
    public function another_users_customer_cannot_be_viewed_or_changed(): void
    {
        $customer = Customer::factory()->for($this->user())->create();
        $intruder = $this->user();

        $this->actingAs($intruder)->get(route('crm.customers.show', $customer))->assertForbidden();
        $this->actingAs($intruder)->patch(route('crm.customers.archive', $customer))->assertForbidden();
    }

    #[Test]
    public function the_profile_page_renders(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create(['name' => 'Профил']);

        $this->actingAs($user)
            ->get(route('crm.customers.show', $customer))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::customers/show')
                ->where('customer.data.name', 'Профил')
                ->has('options.sources'));
    }

    #[Test]
    public function a_customer_without_related_records_goes_to_the_trash(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create(['name' => 'Сгрешен запис']);

        $this->actingAs($user)
            ->delete(route('crm.customers.destroy', $customer))
            ->assertRedirect(route('crm.customers.index'))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('customers', ['id' => $customer->getKey()]);
    }

    #[Test]
    public function a_trashed_customer_can_be_restored(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)->delete(route('crm.customers.destroy', $customer));
        $this->actingAs($user)
            ->patch(route('crm.customers.restore', $customer))
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('customers', ['id' => $customer->getKey()]);
    }

    #[Test]
    public function a_trashed_customer_can_be_deleted_forever(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)->delete(route('crm.customers.destroy', $customer));
        $this->actingAs($user)
            ->delete(route('crm.customers.force-destroy', $customer))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customers', 0);
    }

    #[Test]
    public function the_trash_is_a_separate_view_of_the_customer_list(): void
    {
        $user = $this->user();
        Customer::factory()->for($user)->create(['name' => 'Активен']);
        $deleted = Customer::factory()->for($user)->create(['name' => 'Изтрит']);

        $this->actingAs($user)->delete(route('crm.customers.destroy', $deleted));

        $this->actingAs($user)
            ->get('/crm/customers')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Активен'));

        $this->actingAs($user)
            ->get('/crm/customers?status=deleted')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Изтрит'));
    }

    /**
     * Същината на веригата: запитването пречи, докато не бъде изтрито.
     */
    #[Test]
    public function deleting_the_chain_frees_the_customer(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();
        $opportunity = Opportunity::factory()->for($user)->for($customer)->create();

        $this->actingAs($user)
            ->delete(route('crm.customers.destroy', $customer))
            ->assertSessionHasErrors('delete');

        $this->actingAs($user)
            ->delete(route('crm.opportunities.destroy', $opportunity))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->delete(route('crm.customers.destroy', $customer))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('customers', ['id' => $customer->getKey()]);
    }

    /** Записите в Кошчето не пречат на мекото изтриване, но пречат на окончателното. */
    #[Test]
    public function a_trashed_child_blocks_only_the_permanent_deletion(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();
        $opportunity = Opportunity::factory()->for($user)->for($customer)->create();

        $this->actingAs($user)->delete(route('crm.opportunities.destroy', $opportunity));
        $this->actingAs($user)->delete(route('crm.customers.destroy', $customer))->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->delete(route('crm.customers.force-destroy', $customer))
            ->assertSessionHasErrors('delete');

        $this->assertSoftDeleted('customers', ['id' => $customer->getKey()]);

        $this->actingAs($user)->delete(route('crm.opportunities.force-destroy', $opportunity));
        $this->actingAs($user)
            ->delete(route('crm.customers.force-destroy', $customer))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('customers', 0);
    }

    /** Дете не се връща от Кошчето, докато родителят му е още там. */
    #[Test]
    public function an_opportunity_cannot_be_restored_before_its_customer(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();
        $opportunity = Opportunity::factory()->for($user)->for($customer)->create();

        $this->actingAs($user)->delete(route('crm.opportunities.destroy', $opportunity));
        $this->actingAs($user)->delete(route('crm.customers.destroy', $customer));

        $this->actingAs($user)
            ->patch(route('crm.opportunities.restore', $opportunity))
            ->assertSessionHasErrors('delete');

        $this->assertSoftDeleted('crm_opportunities', ['id' => $opportunity->getKey()]);

        $this->actingAs($user)->patch(route('crm.customers.restore', $customer));
        $this->actingAs($user)
            ->patch(route('crm.opportunities.restore', $opportunity))
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('crm_opportunities', ['id' => $opportunity->getKey()]);
    }

    #[Test]
    public function a_customer_with_an_opportunity_cannot_be_deleted(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();
        Opportunity::factory()->for($user)->for($customer)->create();

        $this->actingAs($user)
            ->delete(route('crm.customers.destroy', $customer))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('crm_opportunities', 1);
    }

    #[Test]
    public function a_customer_with_a_calculation_cannot_be_deleted(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();
        $unit = Unit::factory()->for($user)->create();

        $this->actingAs($user)->post('/calculations', [
            'service_name' => 'Арка',
            'calculated_at' => '2026-03-01',
            'labor_cost' => '40',
            'other_costs' => '0',
            'target_net_margin_percent' => 40,
            'customer_id' => $customer->getKey(),
            'items' => [[
                'material_name' => 'Балони',
                'unit_id' => $unit->getKey(),
                'quantity' => '10',
                'input_mode' => 'total',
                'entered_total_cost' => '40',
            ]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->delete(route('crm.customers.destroy', $customer))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('calculations', 1);
    }

    #[Test]
    public function a_customer_with_a_task_cannot_be_deleted(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)->post('/crm/tasks', [
            'title' => 'Обаждане',
            'type' => 'call',
            'customer_id' => $customer->getKey(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->delete(route('crm.customers.destroy', $customer))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseCount('customers', 1);
    }

    #[Test]
    public function another_users_customer_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->for($this->user())->create();

        $this->actingAs($this->user())
            ->delete(route('crm.customers.destroy', $customer))
            ->assertForbidden();

        $this->assertDatabaseCount('customers', 1);
    }

    #[Test]
    public function the_profile_reports_whether_the_customer_can_be_deleted(): void
    {
        $user = $this->user();

        $clean = Customer::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('crm.customers.show', $clean))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('customer.data.can_be_deleted', true)
                ->where('customer.data.deletion_blockers', []));

        $linked = Customer::factory()->for($user)->create();
        Opportunity::factory()->for($user)->for($linked)->create();

        $this->actingAs($user)
            ->get(route('crm.customers.show', $linked))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('customer.data.can_be_deleted', false)
                ->where('customer.data.deletion_blockers', [
                    ['key' => 'opportunities', 'label' => 'запитване', 'count' => 1],
                ]));
    }

    #[Test]
    public function the_customer_list_does_not_pay_for_deletion_checks(): void
    {
        $user = $this->user();
        Customer::factory()->for($user)->create();

        // Полетата за изтриване се смятат само в профила, не за всеки ред.
        $this->actingAs($user)
            ->get('/crm/customers')
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('customers.data.0.can_be_deleted'));
    }

    #[Test]
    public function the_autocomplete_endpoint_returns_matching_customers(): void
    {
        $user = $this->user();
        Customer::factory()->for($user)->create(['name' => 'Анна Димитрова']);
        Customer::factory()->for($user)->create(['name' => 'Борис Георгиев']);

        $this->actingAs($user)
            ->getJson('/crm/customers/search?q=Анна')
            ->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Анна Димитрова');
    }
}
