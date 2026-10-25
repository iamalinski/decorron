<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrmOverviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_cannot_reach_the_overview(): void
    {
        $this->get('/crm')->assertRedirect('/login');
    }

    #[Test]
    public function the_overview_renders_with_empty_data(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/crm')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::overview')
                ->where('stats.customers', 0)
                ->where('stats.open_opportunities', 0)
                ->has('pipeline', 5)
                ->has('sources', 0));
    }

    #[Test]
    public function the_pipeline_counts_only_open_opportunities(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        Opportunity::factory()->for($user)->for($customer)->create(['stage' => 'pricing']);
        Opportunity::factory()->for($user)->for($customer)->create(['stage' => 'pricing']);
        Opportunity::factory()->for($user)->for($customer)->won()->create(['stage' => 'pricing']);

        $this->actingAs($user)
            ->get('/crm')
            ->assertInertia(function (AssertableInertia $page): void {
                $pipeline = collect($page->toArray()['props']['pipeline']);

                $this->assertSame(2, $pipeline->firstWhere('stage', 'pricing')['count']);
                $this->assertSame(0, $pipeline->firstWhere('stage', 'new')['count']);
            });
    }

    #[Test]
    public function won_and_lost_counts_cover_the_current_month_only(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->for($user)->create();

        Opportunity::factory()->for($user)->for($customer)->create([
            'status' => Opportunity::STATUS_WON,
            'closed_at' => Carbon::now(),
        ]);

        Opportunity::factory()->for($user)->for($customer)->create([
            'status' => Opportunity::STATUS_WON,
            'closed_at' => Carbon::now()->subMonths(2),
        ]);

        $this->actingAs($user)
            ->get('/crm')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('stats.won_this_month', 1));
    }

    #[Test]
    public function the_overview_does_not_show_other_users_data(): void
    {
        $other = User::factory()->create();
        Opportunity::factory()->for($other)->for(Customer::factory()->for($other))->create();

        $this->actingAs(User::factory()->create())
            ->get('/crm')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.open_opportunities', 0)
                ->has('recentOpportunities.data', 0));
    }
}
