<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Models\UserSetting;

/**
 * Данни на бизнеса за клиентските документи (оферти, поръчки).
 *
 * Това НЕ са данъчни настройки — ставките остават само в config/pricing.php.
 */
class CompanySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $settings = UserSetting::forUser($request->user());

        return Inertia::render('core::settings/company', [
            'settings' => [
                'company_name' => $settings->company_name,
                'company_phone' => $settings->company_phone,
                'company_email' => $settings->company_email,
                'company_address' => $settings->company_address,
                'company_extra' => $settings->company_extra,
                'default_quote_terms' => $settings->default_quote_terms,
                'default_quote_validity_days' => (int) $settings->default_quote_validity_days,
            ],
            'fallbackName' => (string) config('app.name'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:160'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'company_email' => ['nullable', 'email:rfc', 'max:160'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'company_extra' => ['nullable', 'string', 'max:255'],
            'default_quote_terms' => ['nullable', 'string', 'max:5000'],
            'default_quote_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], [], [
            'company_name' => 'име на бизнеса',
            'company_phone' => 'телефон',
            'company_email' => 'имейл',
            'company_address' => 'адрес',
            'company_extra' => 'допълнителен ред',
            'default_quote_terms' => 'условия по подразбиране',
            'default_quote_validity_days' => 'валидност на офертите',
        ]);

        UserSetting::forUser($request->user())->update($validated);

        return back()->with('success', 'Данните на бизнеса са запазени.');
    }
}
