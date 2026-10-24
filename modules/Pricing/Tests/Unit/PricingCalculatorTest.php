<?php

declare(strict_types=1);

namespace Modules\Pricing\Tests\Unit;

use Modules\Pricing\Data\CalculationInput;
use Modules\Pricing\Data\CalculationItemInput;
use Modules\Pricing\Services\InvalidCalculationException;
use Modules\Pricing\Services\PricingCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Тестове на финансовия модел.
 *
 * При промяна на данъчните ставки в config/pricing.php очакваните стойности
 * тук трябва да бъдат преизчислени.
 */
class PricingCalculatorTest extends TestCase
{
    private function calculator(): PricingCalculator
    {
        return PricingCalculator::withCurrentRates();
    }

    /** Сценарий A: единична цена на материал от обща стойност. */
    #[Test]
    public function it_derives_unit_price_from_total_cost(): void
    {
        $result = $this->calculator()->calculate(new CalculationInput(
            items: [new CalculationItemInput(quantity: '10', inputMode: 'total', enteredTotalCost: '10')],
            laborCost: '0',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));

        $this->assertSame('1.000000', $result->items[0]->unitPrice);
        $this->assertSame('10.00', $result->items[0]->lineTotal);
        $this->assertSame('10.00', $result->materialCostTotal);
    }

    /** Обратният режим: количество × единична цена. */
    #[Test]
    public function it_derives_line_total_from_unit_price(): void
    {
        $result = $this->calculator()->calculate(new CalculationInput(
            items: [new CalculationItemInput(quantity: '20', inputMode: 'unit', unitPrice: '0.6')],
            laborCost: '0',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));

        $this->assertSame('0.600000', $result->items[0]->unitPrice);
        $this->assertSame('12.00', $result->items[0]->lineTotal);
    }

    /** Сценарий B: материали 40 + труд 40, марж 40% → ≈ 150,33 EUR. */
    #[Test]
    public function it_prices_the_balloon_arch_scenario(): void
    {
        $result = $this->calculator()->calculate(new CalculationInput(
            items: [
                new CalculationItemInput(quantity: '10', inputMode: 'total', enteredTotalCost: '10'),
                new CalculationItemInput(quantity: '20', inputMode: 'total', enteredTotalCost: '12'),
                new CalculationItemInput(quantity: '1', inputMode: 'total', enteredTotalCost: '18'),
            ],
            laborCost: '40',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));

        $this->assertSame('40.00', $result->materialCostTotal);
        $this->assertSame('80.00', $result->totalCost);
        $this->assertSame('150.33', $result->finalPrice);
        $this->assertSame('70.33', $result->profitBeforeTax);
        $this->assertSame('7.03', $result->corporateTaxAmount);
        $this->assertSame('63.30', $result->profitAfterCorporateTax);
        $this->assertSame('3.17', $result->dividendTaxAmount);
        $this->assertSame('60.13', $result->netProfit);

        // Чистата печалба е ≈ 40% от крайната цена.
        $this->assertEqualsWithDelta(0.40, (float) $result->actualNetMargin, 0.0005);
    }

    /** Сценарий C: разходи 120 EUR, марж 40% → ≈ 225,49 EUR. */
    #[Test]
    public function it_prices_a_cost_of_120_euro(): void
    {
        $result = $this->calculator()->calculate(new CalculationInput(
            items: [],
            laborCost: '120',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));

        $this->assertSame('120.00', $result->totalCost);
        $this->assertSame('225.49', $result->finalPrice);
        $this->assertEqualsWithDelta(0.40, (float) $result->actualNetMargin, 0.0005);
    }

    #[Test]
    public function the_net_factor_matches_the_configured_tax_rates(): void
    {
        // (1 - 0.10) * (1 - 0.05) = 0.855
        $this->assertSame('0.855000', $this->calculator()->calculate(new CalculationInput(
            items: [],
            laborCost: '100',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ))->netFactor);
    }

    #[Test]
    public function it_supports_a_zero_margin(): void
    {
        $result = $this->calculator()->calculate(new CalculationInput(
            items: [],
            laborCost: '100',
            otherCosts: '0',
            targetNetMargin: '0',
        ));

        $this->assertSame('100.00', $result->finalPrice);
        $this->assertSame('0.00', $result->netProfit);
    }

    #[Test]
    public function it_rejects_a_margin_at_or_above_the_net_factor(): void
    {
        $this->expectException(InvalidCalculationException::class);

        $this->calculator()->calculate(new CalculationInput(
            items: [],
            laborCost: '100',
            otherCosts: '0',
            targetNetMargin: '0.855',
        ));
    }

    #[Test]
    public function it_rejects_a_negative_margin(): void
    {
        $this->expectException(InvalidCalculationException::class);

        $this->calculator()->calculate(new CalculationInput(
            items: [],
            laborCost: '100',
            otherCosts: '0',
            targetNetMargin: '-0.1',
        ));
    }

    #[Test]
    public function it_rejects_a_zero_quantity(): void
    {
        $this->expectException(InvalidCalculationException::class);

        $this->calculator()->calculate(new CalculationInput(
            items: [new CalculationItemInput(quantity: '0', inputMode: 'total', enteredTotalCost: '10')],
            laborCost: '0',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));
    }

    #[Test]
    public function it_rejects_a_negative_unit_price(): void
    {
        $this->expectException(InvalidCalculationException::class);

        $this->calculator()->calculate(new CalculationInput(
            items: [new CalculationItemInput(quantity: '5', inputMode: 'unit', unitPrice: '-1')],
            laborCost: '0',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));
    }

    #[Test]
    public function it_rejects_a_calculation_without_any_cost(): void
    {
        $this->expectException(InvalidCalculationException::class);

        $this->calculator()->calculate(new CalculationInput(
            items: [],
            laborCost: '0',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));
    }

    /** Закръгляване нагоре: по-висока цена → по-висока печалба и марж. */
    #[Test]
    public function an_overridden_price_recalculates_profit_and_margin(): void
    {
        $result = $this->calculator()->calculate(new CalculationInput(
            items: [],
            laborCost: '80',
            otherCosts: '0',
            targetNetMargin: '0.40',
            finalPriceOverride: '155.00',
        ));

        $this->assertTrue($result->priceWasOverridden);
        $this->assertSame('155.00', $result->finalPrice);
        $this->assertSame('75.00', $result->profitBeforeTax);
        $this->assertSame('7.50', $result->corporateTaxAmount);
        $this->assertSame('3.38', $result->dividendTaxAmount);
        $this->assertSame('64.12', $result->netProfit);
        $this->assertGreaterThan(0.40, (float) $result->actualNetMargin);
    }

    #[Test]
    public function an_overridden_price_below_cost_is_rejected(): void
    {
        $this->expectException(InvalidCalculationException::class);

        $this->calculator()->calculate(new CalculationInput(
            items: [],
            laborCost: '80',
            otherCosts: '0',
            targetNetMargin: '0.40',
            finalPriceOverride: '50.00',
        ));
    }

    /** Единичните цени не се закръгляват преждевременно до два знака. */
    #[Test]
    public function it_keeps_precision_on_repeating_unit_prices(): void
    {
        $result = $this->calculator()->calculate(new CalculationInput(
            items: [new CalculationItemInput(quantity: '3', inputMode: 'total', enteredTotalCost: '10')],
            laborCost: '0',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));

        $this->assertSame('3.333333', $result->items[0]->unitPrice);
        $this->assertSame('10.00', $result->items[0]->lineTotal);
    }

    /** Сумата на редовете не трябва да се разминава с междинната сума. */
    #[Test]
    public function line_totals_add_up_to_the_material_subtotal(): void
    {
        $result = $this->calculator()->calculate(new CalculationInput(
            items: [
                new CalculationItemInput(quantity: '3', inputMode: 'total', enteredTotalCost: '10'),
                new CalculationItemInput(quantity: '7', inputMode: 'total', enteredTotalCost: '5'),
                new CalculationItemInput(quantity: '1.5', inputMode: 'unit', unitPrice: '2.333333'),
            ],
            laborCost: '0',
            otherCosts: '0',
            targetNetMargin: '0.40',
        ));

        $sum = array_sum(array_map(static fn ($i) => (float) $i->lineTotal, $result->items));

        $this->assertEqualsWithDelta($sum, (float) $result->materialCostTotal, 0.001);
        $this->assertSame('18.50', $result->materialCostTotal);
    }
}
