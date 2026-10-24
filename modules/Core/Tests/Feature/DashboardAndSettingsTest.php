<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\UserSetting;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_the_login_screen(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('settings.pricing.edit'))->assertRedirect(route('login'));
    }

    #[Test]
    public function the_dashboard_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('core::dashboard')
                ->where('stats.calculations_count', 0)
                ->has('recentCalculations.data', 0));
    }

    #[Test]
    public function the_root_url_redirects_authenticated_users_to_the_dashboard(): void
    {
        $this->get('/')->assertOk();

        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function the_settings_screen_exposes_the_tax_rates_as_read_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.pricing.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('core::settings/pricing')
                ->where('taxes.corporate', '0.10')
                ->where('taxes.dividend', '0.05')
                ->where('taxes.vat', '0.00')
                ->where('settings.default_net_margin_percent', 40));
    }

    #[Test]
    public function the_default_margin_can_be_changed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.pricing.update'), [
                'default_net_margin_percent' => '35',
                'default_rounding_strategy' => 'ends_9',
            ])
            ->assertRedirect();

        $settings = UserSetting::forUser($user);

        $this->assertSame('0.350000', $settings->default_net_margin);
        $this->assertSame('ends_9', $settings->default_rounding_strategy);
    }

    /** Данъчните ставки не могат да се променят през настройките. */
    #[Test]
    public function tax_rates_cannot_be_changed_through_the_settings_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('settings.pricing.update'), [
            'default_net_margin_percent' => '40',
            'default_rounding_strategy' => 'none',
            'corporate_tax_rate' => '0.01',
            'dividend_tax_rate' => '0.00',
            'vat_rate' => '0.20',
        ])->assertRedirect();

        $this->assertSame('0.10', config('pricing.taxes.corporate'));
        $this->assertSame('0.05', config('pricing.taxes.dividend'));
        $this->assertSame('0.00', config('pricing.taxes.vat'));
        $this->assertDatabaseMissing('user_settings', ['default_net_margin' => '0.010000']);
    }

    #[Test]
    public function an_impossible_default_margin_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.pricing.update'), [
                'default_net_margin_percent' => '90',
                'default_rounding_strategy' => 'none',
            ])
            ->assertSessionHasErrors('default_net_margin_percent');
    }

    #[Test]
    public function an_unknown_rounding_strategy_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.pricing.update'), [
                'default_net_margin_percent' => '40',
                'default_rounding_strategy' => 'magic',
            ])
            ->assertSessionHasErrors('default_rounding_strategy');
    }

    #[Test]
    public function registration_can_be_disabled(): void
    {
        config(['app.registration_enabled' => false]);

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'), [])->assertNotFound();
    }
}
