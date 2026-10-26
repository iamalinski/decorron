import Big from 'big.js';
import { MONEY_SCALE, UNIT_SCALE, big } from './money';

/**
 * Огледало на backend service-а Modules\Pricing\Services\PricingCalculator.
 *
 * Служи САМО за незабавна визуализация докато потребителят пише. Истинските
 * стойности, които се записват, винаги се изчисляват наново на сървъра.
 * При промяна на формулата двата файла трябва да се обновят заедно.
 */

export type InputMode = 'total' | 'unit';

export interface TaxRates {
    corporate: string;
    dividend: string;
    vat: string;
    net_factor: string;
    max_net_margin: string;
}

export interface LineInput {
    quantity: string;
    input_mode: InputMode;
    entered_total_cost: string;
    unit_price: string;
}

export interface LineResult {
    unitPrice: Big;
    lineTotal: Big;
    isValid: boolean;
}

export interface Breakdown {
    ok: true;
    lines: LineResult[];
    materialCostTotal: Big;
    laborCost: Big;
    otherCosts: Big;
    totalCost: Big;
    targetNetMargin: Big;
    netFactor: Big;
    profitBeforeTax: Big;
    corporateTaxAmount: Big;
    profitAfterCorporateTax: Big;
    dividendTaxAmount: Big;
    netProfit: Big;
    finalPrice: Big;
    actualNetMargin: Big;
    priceWasOverridden: boolean;
}

export interface BreakdownError {
    ok: false;
    lines: LineResult[];
    materialCostTotal: Big;
    totalCost: Big;
    message: string;
}

export type BreakdownResult = Breakdown | BreakdownError;

export function netFactorOf(taxes: TaxRates): Big {
    return new Big(1).minus(big(taxes.corporate)).times(new Big(1).minus(big(taxes.dividend)));
}

/**
 * Един материален ред.
 *  - режим „Обща стойност“: unit_price = total / quantity, line_total = total
 *  - режим „Единична цена“: line_total = quantity * unit_price
 */
export function computeLine(line: LineInput): LineResult {
    const quantity = big(line.quantity);

    if (quantity.lte(0)) {
        return { unitPrice: new Big(0), lineTotal: new Big(0), isValid: false };
    }

    if (line.input_mode === 'total') {
        const total = big(line.entered_total_cost);

        if (total.lt(0)) {
            return { unitPrice: new Big(0), lineTotal: new Big(0), isValid: false };
        }

        return {
            unitPrice: total.div(quantity).round(UNIT_SCALE, Big.roundHalfUp),
            lineTotal: total.round(MONEY_SCALE, Big.roundHalfUp),
            isValid: true,
        };
    }

    const unitPrice = big(line.unit_price);

    if (unitPrice.lt(0)) {
        return { unitPrice: new Big(0), lineTotal: new Big(0), isValid: false };
    }

    return {
        unitPrice: unitPrice.round(UNIT_SCALE, Big.roundHalfUp),
        lineTotal: quantity.times(unitPrice).round(MONEY_SCALE, Big.roundHalfUp),
        isValid: true,
    };
}

export interface BreakdownInput {
    lines: LineInput[];
    laborCost: string;
    otherCosts: string;
    marginPercent: string;
    taxes: TaxRates;
    finalPriceOverride?: string | null;
}

export function computeBreakdown(input: BreakdownInput): BreakdownResult {
    const lines = input.lines.map(computeLine);

    const materialCostTotal = lines.reduce((sum, line) => sum.plus(line.lineTotal), new Big(0)).round(MONEY_SCALE, Big.roundHalfUp);

    const laborCost = big(input.laborCost).round(MONEY_SCALE, Big.roundHalfUp);
    const otherCosts = big(input.otherCosts).round(MONEY_SCALE, Big.roundHalfUp);
    const totalCost = materialCostTotal.plus(laborCost).plus(otherCosts).round(MONEY_SCALE, Big.roundHalfUp);

    const fail = (message: string): BreakdownError => ({ ok: false, lines, materialCostTotal, totalCost, message });

    if (laborCost.lt(0) || otherCosts.lt(0)) {
        return fail('Разходите не могат да бъдат отрицателни.');
    }

    if (lines.some((line) => !line.isValid)) {
        return fail('Проверете количествата и цените на материалите.');
    }

    if (totalCost.lte(0)) {
        return fail('Добавете поне един материал, труд или друг разход.');
    }

    const netFactor = netFactorOf(input.taxes);
    const margin = big(input.marginPercent).div(100);

    if (margin.lt(0)) {
        return fail('Желаният чист марж не може да бъде отрицателен.');
    }

    if (margin.gte(netFactor)) {
        return fail(
            `Чистият марж трябва да е под ${netFactor.times(100).round(2, Big.roundDown).toString()}%. ` +
                // Без данъци net_factor е 1 и таванът идва само от това, че
                // маржът е дял от крайната цена.
                (netFactor.eq(1)
                    ? 'Маржът е дял от крайната цена, затова не може да я достигне.'
                    : 'След данъците по-висок чист марж е математически невъзможен.'),
        );
    }

    const priceWasOverridden = input.finalPriceOverride !== null && input.finalPriceOverride !== undefined && input.finalPriceOverride !== '';

    const finalPrice = priceWasOverridden
        ? big(input.finalPriceOverride).round(MONEY_SCALE, Big.roundHalfUp)
        : totalCost.div(new Big(1).minus(margin.div(netFactor))).round(MONEY_SCALE, Big.roundHalfUp);

    if (finalPrice.lt(totalCost)) {
        return fail('Крайната цена не може да бъде по-ниска от общите разходи.');
    }

    const profitBeforeTax = finalPrice.minus(totalCost).round(MONEY_SCALE, Big.roundHalfUp);
    const corporateTaxAmount = profitBeforeTax.times(big(input.taxes.corporate)).round(MONEY_SCALE, Big.roundHalfUp);
    const profitAfterCorporateTax = profitBeforeTax.minus(corporateTaxAmount);
    const dividendTaxAmount = profitAfterCorporateTax.times(big(input.taxes.dividend)).round(MONEY_SCALE, Big.roundHalfUp);
    const netProfit = profitAfterCorporateTax.minus(dividendTaxAmount);

    return {
        ok: true,
        lines,
        materialCostTotal,
        laborCost,
        otherCosts,
        totalCost,
        targetNetMargin: margin,
        netFactor,
        profitBeforeTax,
        corporateTaxAmount,
        profitAfterCorporateTax,
        dividendTaxAmount,
        netProfit,
        finalPrice,
        actualNetMargin: finalPrice.eq(0) ? new Big(0) : netProfit.div(finalPrice).round(6, Big.roundHalfUp),
        priceWasOverridden,
    };
}

export interface RoundingOption {
    key: 'integer' | 'ends_5' | 'ends_9';
    label: string;
    price: Big;
}

/**
 * Предложения за „клиентска“ цена. Винаги нагоре, за да не се реже маржът.
 */
export function roundingOptions(price: Big): RoundingOption[] {
    const base = price.round(MONEY_SCALE, Big.roundHalfUp);
    const options: RoundingOption[] = [];

    const integer = base.round(0, Big.roundUp);
    options.push({ key: 'integer', label: 'До цяло евро', price: integer });

    const toMultipleOfFive = integer.div(5).round(0, Big.roundUp).times(5);
    options.push({ key: 'ends_5', label: 'До кръгла сума (на 5)', price: toMultipleOfFive });

    let endsInNine = Number(integer.toString());
    while (endsInNine % 10 !== 9) {
        endsInNine += 1;
    }
    options.push({ key: 'ends_9', label: 'До цена на 9', price: new Big(endsInNine) });

    // Без дубликати и без стойности под изходната цена.
    const seen = new Set<string>();

    return options.filter((option) => {
        const key = option.price.toFixed(MONEY_SCALE);

        if (seen.has(key) || option.price.lt(base)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}
