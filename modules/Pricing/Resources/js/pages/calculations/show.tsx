import { TrashActions, TrashBanner } from '@/components/trash-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDecimal, formatMoney, formatPercent, formatUnitPrice } from '@/lib/money';
import type { Calculation } from '@/types/decorron';
import { Head, Link } from '@inertiajs/react';
import { Copy, CopyPlus, Printer } from 'lucide-react';
import { toast } from 'sonner';
import { FinancialSummary } from '../../components/financial-summary';

interface ShowPageProps {
    calculation: { data: Calculation };
}

export default function ShowCalculation({ calculation: wrapper }: ShowPageProps) {
    const calculation = wrapper.data;
    const currency = calculation.currency;
    const isTrashed = Boolean(calculation.deleted_at);

    const copyPrice = async () => {
        try {
            await navigator.clipboard.writeText(Number(calculation.final_price).toFixed(2));
            toast.success(`Цената ${formatMoney(calculation.final_price, currency)} е копирана.`);
        } catch {
            toast.error('Копирането не е разрешено от браузъра.');
        }
    };

    const items = calculation.items ?? [];

    return (
        <AppLayout
            title={calculation.service_name}
            description={`${calculation.calculation_number} · ${formatDate(calculation.calculated_at)}`}
            actions={
                <>
                    <Button asChild size="sm" variant="outline" className="hidden sm:inline-flex">
                        <Link href={route('calculations.print', calculation.id)}>
                            <Printer aria-hidden="true" /> Печат
                        </Link>
                    </Button>
                    {!isTrashed && (
                        <Button asChild size="sm">
                            <Link href={route('calculations.duplicate', calculation.id)}>
                                <CopyPlus aria-hidden="true" /> <span className="hidden sm:inline">Дублирай</span>
                            </Link>
                        </Button>
                    )}
                    <TrashActions
                        deletedAt={calculation.deleted_at}
                        blockers={calculation.deletion_blockers}
                        name={`Калкулация ${calculation.calculation_number}`}
                        subject="калкулацията"
                        destroyUrl={route('calculations.destroy', calculation.id)}
                        restoreUrl={route('calculations.restore', calculation.id)}
                        forceUrl={route('calculations.force-destroy', calculation.id)}
                        alsoRemoved="Редовете ѝ я следват; офертите пазят собствено копие на цените."
                    />
                </>
            }
        >
            <Head title={calculation.service_name} />

            {isTrashed && (
                <div className="mx-auto mb-4 max-w-6xl">
                    <TrashBanner>
                        Тази калкулация е в Кошчето. Тя не се показва в историята и в справките, докато не бъде
                        върната.
                    </TrashBanner>
                </div>
            )}

            <div className="mx-auto grid max-w-6xl gap-4 lg:grid-cols-5 lg:items-start">
                <div className="space-y-4 lg:col-span-3">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Данни за услугата</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">Клиент</dt>
                                    <dd className="mt-0.5">
                                        {calculation.customer_id ? (
                                            <Link href={route('crm.customers.show', calculation.customer_id)} className="hover:underline">
                                                {calculation.customer_name ?? calculation.client_name}
                                            </Link>
                                        ) : (
                                            calculation.client_name || '—'
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">Запитване в CRM</dt>
                                    <dd className="mt-0.5">
                                        {calculation.crm_opportunity_id ? (
                                            <Link
                                                href={route('crm.opportunities.show', calculation.crm_opportunity_id)}
                                                className="hover:underline"
                                            >
                                                {calculation.opportunity_title ?? 'Виж запитването'}
                                            </Link>
                                        ) : (
                                            <span className="text-muted-foreground">Не е свързана</span>
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">Дата</dt>
                                    <dd className="mt-0.5">{formatDate(calculation.calculated_at)}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">Използван марж</dt>
                                    <dd className="mt-0.5">
                                        {formatPercent(calculation.target_net_margin)}
                                        {calculation.price_was_rounded && (
                                            <Badge variant="secondary" className="ml-2">
                                                цената е закръглена
                                            </Badge>
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs uppercase tracking-wide text-muted-foreground">Данъци при записване</dt>
                                    <dd className="mt-0.5 text-sm text-muted-foreground">
                                        Корпоративен {formatPercent(calculation.corporate_tax_rate_snapshot, 0)} · дивидент{' '}
                                        {formatPercent(calculation.dividend_tax_rate_snapshot, 0)} · ДДС{' '}
                                        {formatPercent(calculation.vat_rate_snapshot, 0)}
                                    </dd>
                                </div>
                                {calculation.notes && (
                                    <div className="sm:col-span-2">
                                        <dt className="text-xs uppercase tracking-wide text-muted-foreground">Бележка</dt>
                                        <dd className="mt-0.5 whitespace-pre-wrap">{calculation.notes}</dd>
                                    </div>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Материали ({items.length})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {items.length === 0 ? (
                                <p className="py-4 text-center text-sm text-muted-foreground">Калкулацията е без материали.</p>
                            ) : (
                                <>
                                    <ul className="grid gap-3 md:hidden">
                                        {items.map((item) => (
                                            <li key={item.id} className="rounded-lg border p-3">
                                                <div className="flex items-start justify-between gap-2">
                                                    <p className="min-w-0 truncate font-medium">{item.material_name}</p>
                                                    <p className="shrink-0 font-semibold tabular-nums">{formatMoney(item.line_total, currency)}</p>
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {formatDecimal(item.quantity, 2)} {item.unit_symbol} ×{' '}
                                                    {formatUnitPrice(item.unit_price, currency)}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>

                                    <div className="hidden md:block">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Материал</TableHead>
                                                    <TableHead className="text-right">Количество</TableHead>
                                                    <TableHead>Мярка</TableHead>
                                                    <TableHead className="text-right">Единична цена</TableHead>
                                                    <TableHead className="text-right">Сума</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {items.map((item) => (
                                                    <TableRow key={item.id}>
                                                        <TableCell>
                                                            <span className="font-medium">{item.material_name}</span>
                                                            {item.sku && <span className="ml-2 text-xs text-muted-foreground">{item.sku}</span>}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">{formatDecimal(item.quantity, 2)}</TableCell>
                                                        <TableCell className="text-muted-foreground">{item.unit_symbol}</TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {formatUnitPrice(item.unit_price, currency)}
                                                        </TableCell>
                                                        <TableCell className="text-right font-medium tabular-nums">
                                                            {formatMoney(item.line_total, currency)}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="sm:hidden">
                            <Link href={route('calculations.print', calculation.id)}>
                                <Printer aria-hidden="true" /> Печат
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="lg:sticky lg:top-20 lg:col-span-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Финансово обобщение</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FinancialSummary
                                currency={currency}
                                values={{
                                    materialCostTotal: calculation.material_cost_total,
                                    laborCost: calculation.labor_cost,
                                    otherCosts: calculation.other_costs,
                                    totalCost: calculation.total_cost,
                                    profitBeforeTax: calculation.profit_before_tax,
                                    corporateTaxAmount: calculation.corporate_tax_amount,
                                    corporateRate: calculation.corporate_tax_rate_snapshot,
                                    profitAfterCorporateTax: calculation.profit_after_corporate_tax,
                                    dividendTaxAmount: calculation.dividend_tax_amount,
                                    dividendRate: calculation.dividend_tax_rate_snapshot,
                                    vatRate: calculation.vat_rate_snapshot,
                                    netProfit: calculation.net_profit,
                                    finalPrice: calculation.final_price,
                                    actualNetMargin: calculation.actual_net_margin,
                                }}
                                actions={
                                    <Button type="button" size="sm" variant="secondary" onClick={copyPrice}>
                                        <Copy aria-hidden="true" /> Копирай цената
                                    </Button>
                                }
                            />
                            <p className="mt-4 text-xs text-muted-foreground">
                                Показаните стойности са записани към момента на изчислението. Последващи промени в каталога или в настройките не
                                ги променят.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
