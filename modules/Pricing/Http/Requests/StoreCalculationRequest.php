<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Requests;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Pricing\Data\CalculationInput;
use Modules\Pricing\Data\CalculationItemInput;
use Modules\Pricing\Services\PricingCalculator;

/**
 * Валидация на нова (или дублирана) калкулация.
 *
 * Полетата final_price, net_profit, данъците и т.н. НЕ се приемат от клиента —
 * backend-ът ги изчислява сам чрез PricingCalculator. Единственото изключение е
 * final_price_override, което се използва само когато потребителят съзнателно
 * е закръглил цената, и което също минава през пълно преизчисление.
 */
class StoreCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = (int) $this->user()->getKey();

        return [
            'service_name' => ['required', 'string', 'max:160'],
            'client_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'calculated_at' => ['required', 'date'],

            'labor_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'other_costs' => ['required', 'numeric', 'min:0', 'max:99999999'],

            'target_net_margin_percent' => ['required', 'numeric', 'min:0', $this->marginRule()],

            'final_price_override' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],

            'items' => ['present', 'array', 'max:200'],
            'items.*.material_id' => [
                'nullable', 'integer',
                Rule::exists('materials', 'id')->where('user_id', $userId),
            ],
            'items.*.material_name' => ['required', 'string', 'max:160'],
            'items.*.sku' => ['nullable', 'string', 'max:60'],
            'items.*.unit_id' => [
                'required', 'integer',
                Rule::exists('units', 'id')->where('user_id', $userId),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'items.*.input_mode' => ['required', Rule::in([CalculationItemInput::MODE_TOTAL, CalculationItemInput::MODE_UNIT])],
            'items.*.entered_total_cost' => ['nullable', 'required_if:items.*.input_mode,total', 'numeric', 'min:0', 'max:99999999'],
            'items.*.unit_price' => ['nullable', 'required_if:items.*.input_mode,unit', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /** Маржът трябва да е под net_factor, иначе цената клони към безкрайност. */
    private function marginRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $max = BigDecimal::of(PricingCalculator::withCurrentRates()->maximumNetMargin())
                ->multipliedBy(100);

            if (BigDecimal::of((string) $value)->isGreaterThanOrEqualTo($max)) {
                $fail(sprintf(
                    'Желаният чист марж трябва да е под %s%%. При текущите данъчни ставки '
                    .'по-висок чист марж е математически невъзможен.',
                    (string) $max->toScale(2, RoundingMode::DOWN),
                ));
            }
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'service_name' => 'име на услугата',
            'client_name' => 'клиент',
            'notes' => 'бележка',
            'calculated_at' => 'дата',
            'labor_cost' => 'труд',
            'other_costs' => 'други разходи',
            'target_net_margin_percent' => 'желан чист марж',
            'final_price_override' => 'закръглена цена',
            'items' => 'материали',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.*.material_name.required' => 'Всеки ред трябва да има име на материал.',
            'items.*.quantity.gt' => 'Количеството трябва да е по-голямо от нула.',
            'items.*.unit_id.required' => 'Изберете мерна единица за всеки ред.',
            'items.*.unit_id.exists' => 'Избраната мерна единица не съществува.',
            'items.*.entered_total_cost.required_if' => 'Въведете обща стойност за реда.',
            'items.*.unit_price.required_if' => 'Въведете единична цена за реда.',
            'items.*.entered_total_cost.min' => 'Стойността не може да е отрицателна.',
            'items.*.unit_price.min' => 'Цената не може да е отрицателна.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'labor_cost' => $this->input('labor_cost', 0) ?? 0,
            'other_costs' => $this->input('other_costs', 0) ?? 0,
            'items' => is_array($this->input('items')) ? $this->input('items') : [],
        ]);
    }

    /** Входните данни за PricingCalculator — вече валидирани. */
    public function toCalculationInput(): CalculationInput
    {
        $validated = $this->validated();

        $items = array_map(
            fn (array $item): CalculationItemInput => new CalculationItemInput(
                quantity: (string) $item['quantity'],
                inputMode: $item['input_mode'],
                enteredTotalCost: isset($item['entered_total_cost']) ? (string) $item['entered_total_cost'] : null,
                unitPrice: isset($item['unit_price']) ? (string) $item['unit_price'] : null,
            ),
            $validated['items'],
        );

        $margin = BigDecimal::of((string) $validated['target_net_margin_percent'])
            ->dividedBy(100, (int) config('pricing.scale.rate', 6), RoundingMode::HALF_UP);

        return new CalculationInput(
            items: array_values($items),
            laborCost: (string) $validated['labor_cost'],
            otherCosts: (string) $validated['other_costs'],
            targetNetMargin: (string) $margin,
            finalPriceOverride: isset($validated['final_price_override'])
                ? (string) $validated['final_price_override']
                : null,
        );
    }
}
