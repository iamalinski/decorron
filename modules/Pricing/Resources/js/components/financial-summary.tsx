import { formatMoney, formatPercent } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

export interface SummaryValues {
    materialCostTotal: string;
    laborCost: string;
    otherCosts: string;
    totalCost: string;
    profitBeforeTax: string;
    corporateTaxAmount: string;
    corporateRate: string;
    profitAfterCorporateTax: string;
    dividendTaxAmount: string;
    dividendRate: string;
    vatRate: string;
    netProfit: string;
    finalPrice: string;
    actualNetMargin: string;
}

interface FinancialSummaryProps {
    values: SummaryValues;
    currency: string;
    /** Бутони под крайната цена (закръгляване, копиране). */
    actions?: ReactNode;
    /**
     * Заделя ли цената пари за корпоративен данък и данък дивидент.
     * При false разбивката не показва данъчни редове — няма какво да покаже.
     */
    taxesApplied?: boolean;
    /** Превключвателят за данъците — показва се над данъчния блок. */
    taxToggle?: ReactNode;
    className?: string;
}

function Line({ label, value, muted = false, strong = false }: { label: ReactNode; value: string; muted?: boolean; strong?: boolean }) {
    return (
        <div className="flex items-baseline justify-between gap-3 py-1.5">
            <dt className={cn('text-sm', muted ? 'text-muted-foreground' : '')}>{label}</dt>
            <dd className={cn('shrink-0 tabular-nums', strong ? 'font-semibold' : 'text-sm', muted ? 'text-muted-foreground' : '')}>{value}</dd>
        </div>
    );
}

/**
 * Разбивка на разходите, данъците и печалбата.
 * Крайната цена е най-видимият елемент.
 */
export function FinancialSummary({ values, currency, actions, taxesApplied = true, taxToggle, className }: FinancialSummaryProps) {
    return (
        <div className={cn('space-y-4', className)}>
            <dl className="divide-y">
                <div className="pb-1">
                    <Line label="Материали" value={formatMoney(values.materialCostTotal, currency)} />
                    <Line label="Труд" value={formatMoney(values.laborCost, currency)} />
                    <Line label="Други разходи" value={formatMoney(values.otherCosts, currency)} />
                </div>

                <div className="py-1">
                    <Line label="Общо разходи" value={formatMoney(values.totalCost, currency)} strong />
                </div>

                <div className="py-1">
                    {taxToggle}
                    <Line label="Печалба преди данъци" value={formatMoney(values.profitBeforeTax, currency)} />
                    {taxesApplied ? (
                        <>
                            <Line
                                label={`Корпоративен данък (${formatPercent(values.corporateRate, 0)})`}
                                value={`− ${formatMoney(values.corporateTaxAmount, currency)}`}
                                muted
                            />
                            <Line label="Печалба след корпоративен данък" value={formatMoney(values.profitAfterCorporateTax, currency)} muted />
                            <Line
                                label={`Данък дивидент (${formatPercent(values.dividendRate, 0)})`}
                                value={`− ${formatMoney(values.dividendTaxAmount, currency)}`}
                                muted
                            />
                            <Line label={`ДДС (${formatPercent(values.vatRate, 0)})`} value={formatMoney('0', currency)} muted />
                        </>
                    ) : (
                        <Line label="Корпоративен данък, дивидент и ДДС" value="не се начисляват" muted />
                    )}
                </div>

                <div className="py-1">
                    <Line
                        label={taxesApplied ? 'Чиста печалба след данъци' : 'Чиста печалба'}
                        value={formatMoney(values.netProfit, currency)}
                        strong
                    />
                    <Line label="Реален чист марж" value={formatPercent(values.actualNetMargin)} />
                </div>
            </dl>

            <div className="bg-primary text-primary-foreground rounded-xl px-4 py-4">
                <p className="text-xs tracking-wide uppercase opacity-80">Крайна цена за клиента</p>
                <p className="mt-1 text-3xl font-bold tabular-nums sm:text-4xl">{formatMoney(values.finalPrice, currency)}</p>
                {actions && <div className="mt-3 flex flex-wrap gap-2">{actions}</div>}
            </div>
        </div>
    );
}
