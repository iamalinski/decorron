<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Http\Requests\PricingSettingsRequest;
use Modules\Core\Models\UserSetting;
use Modules\Pricing\Services\PricingCalculator;

/**
 * Настройки за ценообразуване.
 *
 * Данъчните ставки се показват само за информация — те идват от
 * config/pricing.php и не могат да бъдат редактирани оттук.
 */
class PricingSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $settings = UserSetting::forUser($request->user());
        $calculator = PricingCalculator::withCurrentRates();

        return Inertia::render('core::settings/pricing', [
            'settings' => [
                'default_net_margin_percent' => (float) $settings->default_net_margin * 100,
                'default_rounding_strategy' => $settings->default_rounding_strategy,
            ],
            'taxes' => [
                'corporate' => (string) $calculator->taxes()->corporate,
                'dividend' => (string) $calculator->taxes()->dividend,
                'vat' => (string) $calculator->taxes()->vat,
                'net_factor' => (string) $calculator->taxes()->netFactor(),
                'max_net_margin' => $calculator->maximumNetMargin(),
            ],
        ]);
    }

    public function update(PricingSettingsRequest $request): RedirectResponse
    {
        $settings = UserSetting::forUser($request->user());

        $settings->update([
            'default_net_margin' => bcdiv(
                (string) $request->validated('default_net_margin_percent'),
                '100',
                (int) config('pricing.scale.rate', 6),
            ),
            'default_rounding_strategy' => $request->validated('default_rounding_strategy'),
        ]);

        return back()->with('success', 'Настройките са запазени. Те важат само за нови калкулации.');
    }
}
