<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Modules\Crm\Models\ActivityLog;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    #[Test]
    public function guests_cannot_reach_opportunities(): void
    {
        $this->get('/crm/opportunities')->assertRedirect('/login');
    }

    #[Test]
    public function an_opportunity_can_be_created(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)->post('/crm/opportunities', [
            'customer_id' => $customer->getKey(),
            'title' => 'Украса за сватба',
            'event_type' => 'Сватба',
            'event_date' => '2026-07-18',
            'stage' => 'new',
        ])->assertSessionHasNoErrors();

        $opportunity = Opportunity::query()->firstOrFail();

        $this->assertSame('Украса за сватба', $opportunity->title);
        $this->assertSame(Opportunity::STATUS_OPEN, $opportunity->status);
        $this->assertSame($user->getKey(), $opportunity->user_id);

        // Създаването оставя автоматичен запис в хронологията.
        $this->assertDatabaseHas('crm_activity_logs', [
            'opportunity_id' => $opportunity->getKey(),
            'type' => ActivityLog::TYPE_OPPORTUNITY_CREATED,
            'is_automatic' => true,
        ]);
    }

    #[Test]
    public function one_customer_can_have_several_opportunities(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();

        foreach (['Рожден ден', 'Кръщене'] as $title) {
            $this->actingAs($user)->post('/crm/opportunities', [
                'customer_id' => $customer->getKey(),
                'title' => $title,
                'stage' => 'new',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Opportunity::query()->where('customer_id', $customer->getKey())->count());
    }

    #[Test]
    public function an_opportunity_for_another_users_customer_is_rejected(): void
    {
        $customer = Customer::factory()->for($this->user())->create();

        $this->actingAs($this->user())
            ->post('/crm/opportunities', [
                'customer_id' => $customer->getKey(),
                'title' => 'Опит за чужд клиент',
                'stage' => 'new',
            ])
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('crm_opportunities', 0);
    }

    #[Test]
    public function a_customer_is_required(): void
    {
        $this->actingAs($this->user())
            ->post('/crm/opportunities', ['title' => 'Без клиент', 'stage' => 'new'])
            ->assertSessionHasErrors('customer_id');
    }

    #[Test]
    public function an_unknown_stage_is_rejected(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/crm/opportunities', [
                'customer_id' => $customer->getKey(),
                'title' => 'Тест',
                'stage' => 'won',
            ])
            ->assertSessionHasErrors('stage');
    }

    #[Test]
    public function the_stage_can_be_changed_and_is_logged(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->create(['stage' => 'new']);

        $this->actingAs($user)
            ->patch(route('crm.opportunities.stage', $opportunity), ['stage' => 'consultation'])
            ->assertSessionHasNoErrors();

        $this->assertSame('consultation', $opportunity->fresh()->stage);
        $this->assertDatabaseHas('crm_activity_logs', [
            'opportunity_id' => $opportunity->getKey(),
            'type' => ActivityLog::TYPE_STAGE_CHANGED,
        ]);
    }

    #[Test]
    public function changing_to_the_same_stage_does_not_create_a_log_entry(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->create(['stage' => 'pricing']);

        $this->actingAs($user)->patch(route('crm.opportunities.stage', $opportunity), ['stage' => 'pricing']);

        $this->assertDatabaseCount('crm_activity_logs', 0);
    }

    #[Test]
    public function a_closed_opportunity_does_not_change_stage(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->won()->create(['stage' => 'negotiation']);

        $this->actingAs($user)
            ->patch(route('crm.opportunities.stage', $opportunity), ['stage' => 'new'])
            ->assertSessionHasErrors('stage');

        $this->assertSame('negotiation', $opportunity->fresh()->stage);
    }

    #[Test]
    public function an_opportunity_can_be_marked_won(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->create();

        $this->actingAs($user)
            ->patch(route('crm.opportunities.close', $opportunity), ['status' => 'won'])
            ->assertSessionHasNoErrors();

        $fresh = $opportunity->fresh();

        $this->assertSame(Opportunity::STATUS_WON, $fresh->status);
        $this->assertNotNull($fresh->closed_at);
        $this->assertDatabaseHas('crm_activity_logs', ['type' => ActivityLog::TYPE_STATUS_CHANGED]);
    }

    #[Test]
    public function a_lost_opportunity_keeps_its_reason(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->create();

        $this->actingAs($user)->patch(route('crm.opportunities.close', $opportunity), [
            'status' => 'lost',
            'lost_reason' => 'competitor',
            'lost_reason_note' => 'Избраха друга фирма.',
        ])->assertSessionHasNoErrors();

        $fresh = $opportunity->fresh();

        $this->assertSame('lost', $fresh->status);
        $this->assertSame('competitor', $fresh->lost_reason);
        $this->assertSame('Избраха друга фирма.', $fresh->lost_reason_note);
    }

    #[Test]
    public function reopening_clears_the_lost_reason(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->lost()->create();

        $this->actingAs($user)->patch(route('crm.opportunities.close', $opportunity), ['status' => 'open']);

        $fresh = $opportunity->fresh();

        $this->assertSame('open', $fresh->status);
        $this->assertNull($fresh->lost_reason);
        $this->assertNull($fresh->closed_at);
    }

    #[Test]
    public function a_manual_note_is_marked_as_manual(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->create();

        $this->actingAs($user)
            ->post(route('crm.opportunities.notes.store', $opportunity), ['description' => 'Говорихме по телефона.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('crm_activity_logs', [
            'opportunity_id' => $opportunity->getKey(),
            'type' => ActivityLog::TYPE_NOTE,
            'is_automatic' => false,
            'description' => 'Говорихме по телефона.',
        ]);
    }

    #[Test]
    public function the_list_shows_only_open_opportunities_by_default(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();
        Opportunity::factory()->for($user)->for($customer)->create(['title' => 'Отворена']);
        Opportunity::factory()->for($user)->for($customer)->won()->create(['title' => 'Спечелена']);

        $this->actingAs($user)
            ->get('/crm/opportunities')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::opportunities/index')
                ->has('opportunities.data', 1)
                ->where('opportunities.data.0.title', 'Отворена'));

        $this->actingAs($user)
            ->get('/crm/opportunities?status=all')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('opportunities.data', 2));
    }

    #[Test]
    public function the_board_view_returns_every_open_opportunity_without_pagination(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();
        Opportunity::factory()->for($user)->for($customer)->count(3)->create();

        $this->actingAs($user)
            ->get('/crm/opportunities?view=board')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('opportunities.data', 3)
                ->missing('opportunities.meta'));
    }

    #[Test]
    public function opportunities_can_be_searched_by_customer_name(): void
    {
        $user = $this->user();
        $wanted = Customer::factory()->for($user)->create(['name' => 'Елена Стоянова']);
        $other = Customer::factory()->for($user)->create(['name' => 'Петър Колев']);

        Opportunity::factory()->for($user)->for($wanted)->create(['title' => 'Арка']);
        Opportunity::factory()->for($user)->for($other)->create(['title' => 'Фотозона']);

        $this->actingAs($user)
            ->get('/crm/opportunities?search=Елена')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('opportunities.data', 1)
                ->where('opportunities.data.0.title', 'Арка'));
    }

    #[Test]
    public function another_users_opportunity_is_not_accessible(): void
    {
        $owner = $this->user();
        $opportunity = Opportunity::factory()->for($owner)->for(Customer::factory()->for($owner))->create();

        $intruder = $this->user();

        $this->actingAs($intruder)->get(route('crm.opportunities.show', $opportunity))->assertForbidden();
        $this->actingAs($intruder)->patch(route('crm.opportunities.stage', $opportunity), ['stage' => 'pricing'])->assertForbidden();
        $this->actingAs($intruder)->patch(route('crm.opportunities.close', $opportunity), ['status' => 'won'])->assertForbidden();
    }

    #[Test]
    public function the_detail_page_shows_the_timeline(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->create();

        $this->actingAs($user)->post(route('crm.opportunities.notes.store', $opportunity), ['description' => 'Първи контакт']);

        $this->actingAs($user)
            ->get(route('crm.opportunities.show', $opportunity))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::opportunities/show')
                ->has('opportunity.data.activities', 1)
                ->where('opportunity.data.activities.0.description', 'Първи контакт')
                ->where('opportunity.data.activities.0.is_automatic', false));
    }

    #[Test]
    public function the_customer_profile_lists_their_opportunities(): void
    {
        $user = $this->user();
        $customer = Customer::factory()->for($user)->create();
        Opportunity::factory()->for($user)->for($customer)->create(['title' => 'Свързано запитване']);

        $this->actingAs($user)
            ->get(route('crm.customers.show', $customer))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('opportunities.data', 1)
                ->where('opportunities.data.0.title', 'Свързано запитване'));
    }

    #[Test]
    public function an_opportunity_goes_to_the_trash_and_can_be_restored(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->create();

        $this->actingAs($user)
            ->delete(route('crm.opportunities.destroy', $opportunity))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('crm_opportunities', ['id' => $opportunity->getKey()]);

        $this->actingAs($user)
            ->get('/crm/opportunities?status=all')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('opportunities.data', 0));

        $this->actingAs($user)
            ->get('/crm/opportunities?status=deleted')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('opportunities.data', 1));

        $this->actingAs($user)->patch(route('crm.opportunities.restore', $opportunity));

        $this->assertNotSoftDeleted('crm_opportunities', ['id' => $opportunity->getKey()]);
    }

    #[Test]
    public function another_users_opportunity_cannot_be_deleted(): void
    {
        $owner = $this->user();
        $opportunity = Opportunity::factory()->for($owner)->for(Customer::factory()->for($owner))->create();

        $this->actingAs($this->user())
            ->delete(route('crm.opportunities.destroy', $opportunity))
            ->assertForbidden();

        $this->assertNotSoftDeleted('crm_opportunities', ['id' => $opportunity->getKey()]);
    }

    /** Изтритото запитване не се брои в таблото и в справките. */
    #[Test]
    public function a_trashed_opportunity_disappears_from_the_crm_overview(): void
    {
        $user = $this->user();
        $opportunity = Opportunity::factory()->for($user)->for(Customer::factory()->for($user))->create();

        $this->actingAs($user)->delete(route('crm.opportunities.destroy', $opportunity));

        $this->actingAs($user)
            ->get('/crm')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.open_opportunities', 0));
    }
}
