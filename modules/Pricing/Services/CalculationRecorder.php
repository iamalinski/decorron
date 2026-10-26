<?php

declare(strict_types=1);

namespace Modules\Pricing\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;
use Modules\Pricing\Data\CalculationResult;
use Modules\Pricing\Models\Calculation;
use Modules\Pricing\Support\TaxRates;

/**
 * Записва калкулация заедно с редовете ѝ в една транзакция и прави
 * snapshot на всичко, от което зависи резултатът: имена на материали,
 * мерни единици, единични цени, марж и данъчни ставки.
 */
final class CalculationRecorder
{
    public function __construct(private readonly PricingCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $attributes  валидираните данни от заявката
     */
    public function record(User $user, array $attributes, CalculationResult $result): Calculation
    {
        $units = $this->unitsFor($user, $attributes['items']);
        $materials = $this->materialsFor($user, $attributes['items']);

        return DB::transaction(function () use ($user, $attributes, $result, $units, $materials): Calculation {
            $calculatedAt = Carbon::parse($attributes['calculated_at']);

            $calculation = new Calculation([
                'calculation_number' => Calculation::nextNumberFor($user, (int) $calculatedAt->year),
                'service_name' => $attributes['service_name'],
                'client_name' => $attributes['client_name'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'calculated_at' => $calculatedAt->toDateString(),
                'material_cost_total' => $result->materialCostTotal,
                'labor_cost' => $result->laborCost,
                'other_costs' => $result->otherCosts,
                'total_cost' => $result->totalCost,
                'target_net_margin' => $result->targetNetMargin,
                // Ставките идват от резултата, а не от текущата конфигурация:
                // така snapshot-ът винаги отговаря на това, с което е смятано.
                'corporate_tax_rate_snapshot' => $result->corporateRate,
                'dividend_tax_rate_snapshot' => $result->dividendRate,
                'vat_rate_snapshot' => $result->vatRate,
                'profit_before_tax' => $result->profitBeforeTax,
                'corporate_tax_amount' => $result->corporateTaxAmount,
                'profit_after_corporate_tax' => $result->profitAfterCorporateTax,
                'dividend_tax_amount' => $result->dividendTaxAmount,
                'net_profit' => $result->netProfit,
                'final_price' => $result->finalPrice,
                'actual_net_margin' => $result->actualNetMargin,
                'price_was_rounded' => $result->priceWasOverridden,
                'taxes_applied' => $result->taxesApplied,
                'currency' => (string) config('pricing.currency', 'EUR'),
            ]);

            $calculation->user_id = $user->getKey();
            $calculation->save();

            foreach ($attributes['items'] as $position => $item) {
                $unit = $units[(int) $item['unit_id']] ?? null;
                $material = isset($item['material_id']) ? ($materials[(int) $item['material_id']] ?? null) : null;
                $computed = $result->items[$position];

                $calculation->items()->create([
                    'material_id' => $material?->getKey(),
                    'unit_id' => $unit?->getKey(),
                    'material_name_snapshot' => trim((string) $item['material_name']),
                    'sku_snapshot' => $item['sku'] ?? $material?->sku,
                    'unit_name_snapshot' => $unit?->name ?? '—',
                    'unit_symbol_snapshot' => $unit?->symbol ?? '—',
                    'quantity' => $computed->quantity,
                    'input_mode' => $computed->inputMode,
                    'entered_total_cost' => $computed->enteredTotalCost,
                    'unit_price' => $computed->unitPrice,
                    'line_total' => $computed->lineTotal,
                    'position' => $position,
                ]);
            }

            return $calculation->load('items');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, Unit>
     */
    private function unitsFor(User $user, array $items): array
    {
        $ids = array_values(array_unique(array_map(static fn (array $i): int => (int) $i['unit_id'], $items)));

        if ($ids === []) {
            return [];
        }

        return Unit::query()->ownedBy($user)->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, Material>
     */
    private function materialsFor(User $user, array $items): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $i): ?int => isset($i['material_id']) ? (int) $i['material_id'] : null,
            $items,
        )));

        if ($ids === []) {
            return [];
        }

        return Material::query()->ownedBy($user)->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    /** Ставките, с които този recorder работи (за snapshot и за тестове). */
    public function taxes(): TaxRates
    {
        return $this->calculator->taxes();
    }
}
