<?php

declare(strict_types=1);

namespace Modules\Pricing\Services;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Modules\Pricing\Data\CalculationInput;
use Modules\Pricing\Data\CalculationItemInput;
use Modules\Pricing\Data\CalculationItemResult;
use Modules\Pricing\Data\CalculationResult;
use Modules\Pricing\Support\TaxRates;

/**
 * Централният финансов service. Backend-ът винаги преизчислява резултата
 * сам — стойностите, изпратени от frontend-а, се използват само за
 * незабавна визуализация и никога не се приемат на доверие.
 *
 * Аритметиката е decimal (brick/math), без floating point.
 */
final class PricingCalculator
{
    private readonly int $moneyScale;

    private readonly int $unitScale;

    private readonly int $quantityScale;

    private readonly int $rateScale;

    private readonly int $internalScale;

    public function __construct(private readonly TaxRates $taxes)
    {
        $this->moneyScale = (int) config('pricing.scale.money', 2);
        $this->unitScale = (int) config('pricing.scale.unit', 6);
        $this->quantityScale = (int) config('pricing.scale.quantity', 6);
        $this->rateScale = (int) config('pricing.scale.rate', 6);
        $this->internalScale = (int) config('pricing.scale.internal', 12);
    }

    public static function withCurrentRates(): self
    {
        return new self(TaxRates::current());
    }

    public function taxes(): TaxRates
    {
        return $this->taxes;
    }

    /**
     * @throws InvalidCalculationException
     */
    public function calculate(CalculationInput $input): CalculationResult
    {
        try {
            return $this->compute($input);
        } catch (MathException $e) {
            throw new InvalidCalculationException(
                'Въведените стойности не позволяват изчисление: '.$e->getMessage(),
                'items',
            );
        }
    }

    private function compute(CalculationInput $input): CalculationResult
    {
        $itemResults = [];
        $materialTotal = BigDecimal::zero();

        foreach ($input->items as $item) {
            $result = $this->computeItem($item);
            $itemResults[] = $result;
            $materialTotal = $materialTotal->plus($result->lineTotal);
        }

        $materialTotal = $materialTotal->toScale($this->moneyScale, RoundingMode::HALF_UP);
        $labor = $this->money($input->laborCost);
        $other = $this->money($input->otherCosts);

        if ($labor->isNegative() || $other->isNegative()) {
            throw new InvalidCalculationException('Разходите не могат да бъдат отрицателни.', 'labor_cost');
        }

        $totalCost = $materialTotal->plus($labor)->plus($other)->toScale($this->moneyScale, RoundingMode::HALF_UP);

        if ($totalCost->isLessThanOrEqualTo(0)) {
            throw new InvalidCalculationException(
                'Общите разходи трябва да са по-големи от нула. Добавете поне един материал, труд или друг разход.',
                'total_cost',
            );
        }

        $margin = BigDecimal::of($input->targetNetMargin);
        $netFactor = $this->taxes->netFactor();

        $this->assertMarginIsUsable($margin, $netFactor);

        $finalPrice = $input->finalPriceOverride !== null
            ? $this->money($input->finalPriceOverride)
            : $this->priceForTargetMargin($totalCost, $margin, $netFactor);

        if ($finalPrice->isLessThan($totalCost)) {
            throw new InvalidCalculationException(
                'Крайната цена не може да бъде по-ниска от общите разходи.',
                'final_price',
            );
        }

        // Данъците се извеждат от вече закръглената крайна цена, за да е
        // разбивката на екрана винаги вътрешно съгласувана.
        $profitBeforeTax = $finalPrice->minus($totalCost)->toScale($this->moneyScale, RoundingMode::HALF_UP);
        $corporateTax = $profitBeforeTax->multipliedBy($this->taxes->corporate)
            ->toScale($this->moneyScale, RoundingMode::HALF_UP);
        $profitAfterCorporateTax = $profitBeforeTax->minus($corporateTax);
        $dividendTax = $profitAfterCorporateTax->multipliedBy($this->taxes->dividend)
            ->toScale($this->moneyScale, RoundingMode::HALF_UP);
        $netProfit = $profitAfterCorporateTax->minus($dividendTax);

        $actualMargin = $finalPrice->isZero()
            ? BigDecimal::zero()->toScale($this->rateScale)
            : $netProfit->dividedBy($finalPrice, $this->rateScale, RoundingMode::HALF_UP);

        return new CalculationResult(
            items: $itemResults,
            materialCostTotal: (string) $materialTotal,
            laborCost: (string) $labor,
            otherCosts: (string) $other,
            totalCost: (string) $totalCost,
            targetNetMargin: (string) $margin->toScale($this->rateScale, RoundingMode::HALF_UP),
            netFactor: (string) $netFactor->toScale($this->rateScale, RoundingMode::HALF_UP),
            profitBeforeTax: (string) $profitBeforeTax,
            corporateTaxAmount: (string) $corporateTax,
            profitAfterCorporateTax: (string) $profitAfterCorporateTax->toScale($this->moneyScale, RoundingMode::HALF_UP),
            dividendTaxAmount: (string) $dividendTax,
            netProfit: (string) $netProfit->toScale($this->moneyScale, RoundingMode::HALF_UP),
            finalPrice: (string) $finalPrice,
            actualNetMargin: (string) $actualMargin,
            priceWasOverridden: $input->finalPriceOverride !== null,
        );
    }

    /**
     * Един материален ред.
     *
     * Режим "Обща стойност": потребителят въвежда количество и обща сума,
     * системата извежда единичната цена (unit_price = total / quantity).
     * Общата сума на реда остава точно въведената стойност, за да не се
     * натрупва грешка от закръгляването на единичната цена.
     *
     * Режим "Единична цена": line_total = quantity * unit_price.
     */
    private function computeItem(CalculationItemInput $item): CalculationItemResult
    {
        $quantity = BigDecimal::of($item->quantity);

        if ($quantity->isLessThanOrEqualTo(0)) {
            throw new InvalidCalculationException('Количеството трябва да е по-голямо от нула.', 'items');
        }

        if ($item->inputMode === CalculationItemInput::MODE_TOTAL) {
            $enteredTotal = $this->money((string) ($item->enteredTotalCost ?? '0'));

            if ($enteredTotal->isNegative()) {
                throw new InvalidCalculationException('Общата стойност на реда не може да е отрицателна.', 'items');
            }

            $unitPrice = $enteredTotal->dividedBy($quantity, $this->unitScale, RoundingMode::HALF_UP);

            return new CalculationItemResult(
                quantity: (string) $quantity->toScale($this->quantityScale, RoundingMode::HALF_UP),
                inputMode: CalculationItemInput::MODE_TOTAL,
                enteredTotalCost: (string) $enteredTotal,
                unitPrice: (string) $unitPrice,
                lineTotal: (string) $enteredTotal,
            );
        }

        $unitPrice = BigDecimal::of((string) ($item->unitPrice ?? '0'))
            ->toScale($this->unitScale, RoundingMode::HALF_UP);

        if ($unitPrice->isNegative()) {
            throw new InvalidCalculationException('Единичната цена не може да е отрицателна.', 'items');
        }

        $lineTotal = $quantity->multipliedBy($unitPrice)->toScale($this->moneyScale, RoundingMode::HALF_UP);

        return new CalculationItemResult(
            quantity: (string) $quantity->toScale($this->quantityScale, RoundingMode::HALF_UP),
            inputMode: CalculationItemInput::MODE_UNIT,
            enteredTotalCost: null,
            unitPrice: (string) $unitPrice,
            lineTotal: (string) $lineTotal,
        );
    }

    /**
     * P = C / (1 - m / net_factor)
     */
    private function priceForTargetMargin(BigDecimal $totalCost, BigDecimal $margin, BigDecimal $netFactor): BigDecimal
    {
        $ratio = $margin->dividedBy($netFactor, $this->internalScale, RoundingMode::HALF_UP);
        $denominator = BigDecimal::one()->minus($ratio);

        return $totalCost
            ->dividedBy($denominator, $this->internalScale, RoundingMode::HALF_UP)
            ->toScale($this->moneyScale, RoundingMode::HALF_UP);
    }

    private function assertMarginIsUsable(BigDecimal $margin, BigDecimal $netFactor): void
    {
        if ($margin->isNegative()) {
            throw new InvalidCalculationException('Желаният чист марж не може да бъде отрицателен.');
        }

        if ($margin->isGreaterThanOrEqualTo($netFactor)) {
            $limit = $netFactor->multipliedBy(100)->toScale(2, RoundingMode::DOWN);

            throw new InvalidCalculationException(
                "При текущите данъчни ставки чистият марж трябва да е под {$limit}%. "
                .'След корпоративния данък и данъка върху дивидента от печалбата остава '
                ."само {$limit}% от нея, затова по-висок чист марж е математически невъзможен.",
            );
        }
    }

    /** Максималният чист марж, постижим при текущите ставки (изключващо). */
    public function maximumNetMargin(): string
    {
        return (string) $this->taxes->netFactor()->toScale($this->rateScale, RoundingMode::DOWN);
    }

    private function money(string $value): BigDecimal
    {
        return BigDecimal::of($value)->toScale($this->moneyScale, RoundingMode::HALF_UP);
    }
}
