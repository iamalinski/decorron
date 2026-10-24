<?php

declare(strict_types=1);

namespace Modules\Pricing\Data;

/**
 * Резултатът от PricingCalculator. Всички парични стойности са низове
 * с фиксиран брой знаци, за да няма загуба на точност при предаване.
 */
final readonly class CalculationResult
{
    /**
     * @param  list<CalculationItemResult>  $items
     */
    public function __construct(
        public array $items,
        public string $materialCostTotal,
        public string $laborCost,
        public string $otherCosts,
        public string $totalCost,
        public string $targetNetMargin,
        public string $netFactor,
        public string $profitBeforeTax,
        public string $corporateTaxAmount,
        public string $profitAfterCorporateTax,
        public string $dividendTaxAmount,
        public string $netProfit,
        public string $finalPrice,
        public string $actualNetMargin,
        public bool $priceWasOverridden,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (CalculationItemResult $i) => $i->toArray(), $this->items),
            'material_cost_total' => $this->materialCostTotal,
            'labor_cost' => $this->laborCost,
            'other_costs' => $this->otherCosts,
            'total_cost' => $this->totalCost,
            'target_net_margin' => $this->targetNetMargin,
            'net_factor' => $this->netFactor,
            'profit_before_tax' => $this->profitBeforeTax,
            'corporate_tax_amount' => $this->corporateTaxAmount,
            'profit_after_corporate_tax' => $this->profitAfterCorporateTax,
            'dividend_tax_amount' => $this->dividendTaxAmount,
            'net_profit' => $this->netProfit,
            'final_price' => $this->finalPrice,
            'actual_net_margin' => $this->actualNetMargin,
            'price_was_overridden' => $this->priceWasOverridden,
        ];
    }
}
