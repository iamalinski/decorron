<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Models\UserSetting;
use Modules\Pricing\Services\PricingCalculator;

class PricingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'default_net_margin_percent' => [
                'required', 'numeric', 'min:0',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $max = BigDecimal::of(PricingCalculator::withCurrentRates()->maximumNetMargin())
                        ->multipliedBy(100);

                    if (BigDecimal::of((string) $value)->isGreaterThanOrEqualTo($max)) {
                        $fail('Маржът по подразбиране трябва да е под '.$max->toScale(2, RoundingMode::DOWN).'%.');
                    }
                },
            ],
            'default_rounding_strategy' => ['required', Rule::in(UserSetting::ROUNDING_STRATEGIES)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'default_net_margin_percent' => 'марж по подразбиране',
            'default_rounding_strategy' => 'закръгляване по подразбиране',
        ];
    }
}
