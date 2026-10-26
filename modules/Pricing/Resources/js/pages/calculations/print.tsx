import { Button } from '@/components/ui/button';
import { formatDate, formatDecimal, formatMoney, formatPercent, formatUnitPrice } from '@/lib/money';
import type { Calculation } from '@/types/decorron';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useEffect } from 'react';

interface PrintPageProps {
    calculation: { data: Calculation };
    company: { name: string };
}

/**
 * Компактна версия за печат. Браузърът може да я запази като PDF
 * („Печат“ → „Запази като PDF“), без да е нужна PDF библиотека на сървъра.
 */
export default function PrintCalculation({ calculation: wrapper, company }: PrintPageProps) {
    const calculation = wrapper.data;
    const currency = calculation.currency;
    const items = calculation.items ?? [];

    // Изгледът за печат е винаги светъл, независимо от избраната тема.
    useEffect(() => {
        const wasDark = document.documentElement.classList.contains('dark');

        document.documentElement.classList.remove('dark');
        document.body.classList.add('bg-white');

        return () => {
            document.documentElement.classList.toggle('dark', wasDark);
            document.body.classList.remove('bg-white');
        };
    }, []);

    return (
        <div className="min-h-svh bg-white text-black">
            <Head title={`Печат · ${calculation.calculation_number}`} />

            <div className="mx-auto max-w-3xl px-6 py-6 print:px-0 print:py-0">
                <div className="mb-6 flex items-center justify-between gap-3 print:hidden">
                    <Button asChild variant="outline" size="sm">
                        <Link href={route('calculations.show', calculation.id)}>
                            <ArrowLeft aria-hidden="true" /> Назад
                        </Link>
                    </Button>
                    <Button type="button" size="sm" onClick={() => window.print()}>
                        <Printer aria-hidden="true" /> Печат / PDF
                    </Button>
                </div>

                <header className="flex items-start justify-between gap-4 border-b border-neutral-300 pb-4">
                    <div>
                        <p className="text-sm font-semibold tracking-wide uppercase">{company.name}</p>
                        <h1 className="mt-1 text-2xl font-bold">{calculation.service_name}</h1>
                        {calculation.client_name && <p className="text-sm">Клиент: {calculation.client_name}</p>}
                    </div>
                    <div className="text-right text-sm">
                        <p className="font-medium">{calculation.calculation_number}</p>
                        <p>{formatDate(calculation.calculated_at)}</p>
                    </div>
                </header>

                {items.length > 0 && (
                    <section className="mt-6">
                        <h2 className="mb-2 text-sm font-semibold tracking-wide uppercase">Материали</h2>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-neutral-300 text-left">
                                    <th className="py-1.5 font-medium">Материал</th>
                                    <th className="py-1.5 pr-3 text-right font-medium">Кол.</th>
                                    <th className="py-1.5 font-medium">Мярка</th>
                                    <th className="py-1.5 text-right font-medium">Ед. цена</th>
                                    <th className="py-1.5 text-right font-medium">Сума</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr key={item.id} className="border-b border-neutral-200">
                                        <td className="py-1.5">{item.material_name}</td>
                                        <td className="py-1.5 pr-3 text-right tabular-nums">{formatDecimal(item.quantity, 2)}</td>
                                        <td className="py-1.5">{item.unit_symbol}</td>
                                        <td className="py-1.5 text-right tabular-nums">{formatUnitPrice(item.unit_price, currency)}</td>
                                        <td className="py-1.5 text-right tabular-nums">{formatMoney(item.line_total, currency)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}

                <section className="mt-6">
                    <h2 className="mb-2 text-sm font-semibold tracking-wide uppercase">Разходи и печалба</h2>
                    <dl className="text-sm">
                        <Row label="Материали" value={formatMoney(calculation.material_cost_total, currency)} />
                        <Row label="Труд" value={formatMoney(calculation.labor_cost, currency)} />
                        <Row label="Други разходи" value={formatMoney(calculation.other_costs, currency)} />
                        <Row label="Общо разходи" value={formatMoney(calculation.total_cost, currency)} bold />
                        <Row label="Печалба преди данъци" value={formatMoney(calculation.profit_before_tax, currency)} />
                        {calculation.taxes_applied && (
                            <>
                                <Row
                                    label={`Корпоративен данък (${formatPercent(calculation.corporate_tax_rate_snapshot, 0)})`}
                                    value={`− ${formatMoney(calculation.corporate_tax_amount, currency)}`}
                                />
                                <Row
                                    label={`Данък дивидент (${formatPercent(calculation.dividend_tax_rate_snapshot, 0)})`}
                                    value={`− ${formatMoney(calculation.dividend_tax_amount, currency)}`}
                                />
                            </>
                        )}
                        <Row label="Чиста печалба" value={formatMoney(calculation.net_profit, currency)} bold />
                        <Row label="Чист марж" value={formatPercent(calculation.actual_net_margin)} />
                    </dl>
                </section>

                <section className="mt-6 border-t-2 border-black pt-3">
                    <div className="flex items-baseline justify-between">
                        <p className="text-base font-semibold">Крайна цена</p>
                        <p className="text-2xl font-bold tabular-nums">{formatMoney(calculation.final_price, currency)}</p>
                    </div>
                    <p className="mt-1 text-xs">
                        Цените са в {currency}.{' '}
                        {calculation.taxes_applied
                            ? `ДДС не се начислява (${formatPercent(calculation.vat_rate_snapshot, 0)}).`
                            : 'Цената е изчислена без корпоративен данък, данък дивидент и ДДС.'}
                    </p>
                </section>

                {calculation.notes && (
                    <section className="mt-6 text-sm">
                        <h2 className="mb-1 text-sm font-semibold tracking-wide uppercase">Бележка</h2>
                        <p className="whitespace-pre-wrap">{calculation.notes}</p>
                    </section>
                )}
            </div>
        </div>
    );
}

function Row({ label, value, bold = false }: { label: string; value: string; bold?: boolean }) {
    return (
        <div className={`flex justify-between border-b border-neutral-200 py-1 ${bold ? 'font-semibold' : ''}`}>
            <dt>{label}</dt>
            <dd className="tabular-nums">{value}</dd>
        </div>
    );
}
