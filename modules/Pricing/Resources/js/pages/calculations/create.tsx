import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { NumericInput } from '@/components/numeric-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatMoney, formatPercent, today } from '@/lib/money';
import { computeBreakdown, computeLine, type BreakdownInput, type LineInput, type TaxRates } from '@/lib/pricing';
import { cn } from '@/lib/utils';
import type { Calculation, CalculationDefaults, Material, Unit } from '@/types/decorron';
import type { Collection, SharedData } from '@/types';
import { MaterialFormDialog } from '@modules/Catalog/Resources/js/components/material-form-dialog';
import { Head, router, usePage } from '@inertiajs/react';
import Big from 'big.js';
import { AlertCircle, Copy, Loader2, Package, Plus, Save, Sparkles } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { FinancialSummary } from '../../components/financial-summary';
import { newLine, type LineState } from '../../components/line-types';
import { MaterialRow } from '../../components/material-row';
import { RoundingDialog } from '../../components/rounding-dialog';

interface CreatePageProps {
    units: Collection<Unit>;
    materials: Collection<Material>;
    defaults: CalculationDefaults;
    taxes: TaxRates;
    duplicateOf?: { data: Calculation };
    errors: Record<string, string>;
}

const DRAFT_KEY = 'decorron:calculation-draft:v1';

const MARGIN_PRESETS = ['30', '35', '40', '50'];

function toLineInput(line: LineState): LineInput {
    return {
        quantity: line.quantity,
        input_mode: line.input_mode,
        entered_total_cost: line.entered_total_cost,
        unit_price: line.unit_price,
    };
}

interface DraftShape {
    serviceName: string;
    clientName: string;
    notes: string;
    calculatedAt: string;
    laborCost: string;
    otherCosts: string;
    marginPercent: string;
    lines: LineState[];
    savedAt: number;
}

export default function CreateCalculation({ units, materials, defaults, taxes, duplicateOf }: CreatePageProps) {
    const page = usePage<SharedData>();
    const currency = page.props.app.currency;
    const errors = page.props.errors as Record<string, string>;

    const unitList = units.data;
    const materialList = materials.data;
    const fallbackUnitId = defaults.default_unit_id ?? unitList[0]?.id ?? '';

    const source = duplicateOf?.data;

    const [serviceName, setServiceName] = useState(source ? source.service_name : '');
    const [clientName, setClientName] = useState(source?.client_name ?? '');
    const [notes, setNotes] = useState(source?.notes ?? '');
    const [calculatedAt, setCalculatedAt] = useState(defaults.calculated_at || today());
    const [laborCost, setLaborCost] = useState(source ? source.labor_cost : '');
    const [otherCosts, setOtherCosts] = useState(source ? source.other_costs : '');
    const [marginPercent, setMarginPercent] = useState(
        source ? new Big(source.target_net_margin).times(100).round(2).toString() : String(defaults.net_margin_percent),
    );

    const [lines, setLines] = useState<LineState[]>(() => {
        if (source?.items?.length) {
            return source.items.map((item) => ({
                ...newLine(item.unit_id ?? fallbackUnitId),
                material_id: item.material_id,
                material_name: item.material_name,
                sku: item.sku,
                unit_id: String(item.unit_id ?? fallbackUnitId),
                quantity: new Big(item.quantity).toString(),
                input_mode: item.input_mode,
                entered_total_cost: item.entered_total_cost ?? '',
                unit_price: new Big(item.unit_price).toString(),
            }));
        }

        return [newLine(fallbackUnitId)];
    });

    const [priceOverride, setPriceOverride] = useState<string | null>(null);
    const [roundingOpen, setRoundingOpen] = useState(false);
    const [materialDialogOpen, setMaterialDialogOpen] = useState(false);
    const [materialDialogName, setMaterialDialogName] = useState('');
    const [pendingLineKey, setPendingLineKey] = useState<string | null>(null);
    const [catalog, setCatalog] = useState<Material[]>(materialList);
    const [processing, setProcessing] = useState(false);
    const [draftOffer, setDraftOffer] = useState<DraftShape | null>(null);
    const [draftRestored, setDraftRestored] = useState(false);

    const idempotencyKey = useRef<string>(crypto.randomUUID());
    const listRef = useRef<HTMLUListElement>(null);

    /**
     * Ред влиза в сумата само когато има име и положително количество —
     * същото условие важи и при записване, за да съвпада показаната цена
     * с тази, която backend-ът ще изчисли.
     */
    const usableLines = useMemo(
        () => lines.filter((line) => line.material_name.trim() !== '' && Number(line.quantity) > 0),
        [lines],
    );

    // Всеки ред се изчислява отделно, за да покаже собствената си сума.
    const rowResults = useMemo(() => lines.map((line) => computeLine(toLineInput(line))), [lines]);

    const breakdownInput: BreakdownInput = useMemo(
        () => ({
            lines: usableLines.map(toLineInput),
            laborCost: laborCost || '0',
            otherCosts: otherCosts || '0',
            marginPercent: marginPercent || '0',
            taxes,
        }),
        [usableLines, laborCost, otherCosts, marginPercent, taxes],
    );

    const base = useMemo(() => computeBreakdown(breakdownInput), [breakdownInput]);
    const result = useMemo(
        () => (priceOverride ? computeBreakdown({ ...breakdownInput, finalPriceOverride: priceOverride }) : base),
        [breakdownInput, priceOverride, base],
    );

    /* ---------------- Чернова ---------------- */

    useEffect(() => {
        if (source) {
            return;
        }

        try {
            const raw = localStorage.getItem(DRAFT_KEY);

            if (!raw) {
                return;
            }

            const draft = JSON.parse(raw) as DraftShape;

            // Предлагаме възстановяване само ако има реално въведено съдържание.
            const hasContent = draft.serviceName.trim() !== '' || draft.lines.some((line) => line.material_name.trim() !== '');

            if (hasContent) {
                setDraftOffer(draft);
            }
        } catch {
            localStorage.removeItem(DRAFT_KEY);
        }
        // Само при първо зареждане.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (source) {
            return;
        }

        const timer = window.setTimeout(() => {
            const draft: DraftShape = { serviceName, clientName, notes, calculatedAt, laborCost, otherCosts, marginPercent, lines, savedAt: Date.now() };

            try {
                localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
            } catch {
                /* localStorage може да е недостъпен — черновата просто не се пази. */
            }
        }, 600);

        return () => window.clearTimeout(timer);
    }, [serviceName, clientName, notes, calculatedAt, laborCost, otherCosts, marginPercent, lines, source]);

    const restoreDraft = () => {
        if (!draftOffer) {
            return;
        }

        setServiceName(draftOffer.serviceName);
        setClientName(draftOffer.clientName);
        setNotes(draftOffer.notes);
        setCalculatedAt(draftOffer.calculatedAt || today());
        setLaborCost(draftOffer.laborCost);
        setOtherCosts(draftOffer.otherCosts);
        setMarginPercent(draftOffer.marginPercent);
        setLines(draftOffer.lines.length ? draftOffer.lines : [newLine(fallbackUnitId)]);
        setDraftOffer(null);
        setDraftRestored(true);
        toast.success('Черновата е възстановена.');
    };

    const discardDraft = () => {
        localStorage.removeItem(DRAFT_KEY);
        setDraftOffer(null);
    };

    /* ---------------- Редове ---------------- */

    const updateLine = useCallback((key: string, patch: Partial<LineState>) => {
        setLines((current) => current.map((line) => (line.key === key ? { ...line, ...patch } : line)));
    }, []);

    const addLine = () => {
        const line = newLine(fallbackUnitId);
        setLines((current) => [...current, line]);

        window.setTimeout(() => {
            listRef.current?.querySelector<HTMLButtonElement>(`#row-${line.key}`)?.focus();
        }, 50);
    };

    const removeLine = (key: string) => {
        setLines((current) => (current.length === 1 ? [newLine(fallbackUnitId)] : current.filter((line) => line.key !== key)));
    };

    const openMaterialDialog = (lineKey: string, name: string) => {
        setPendingLineKey(lineKey);
        setMaterialDialogName(name);
        setMaterialDialogOpen(true);
    };

    const handleMaterialCreated = (material: Material) => {
        setCatalog((current) => [material, ...current.filter((item) => item.id !== material.id)]);

        if (pendingLineKey) {
            updateLine(pendingLineKey, {
                material_id: material.id,
                material_name: material.name,
                sku: material.sku,
                unit_id: String(material.unit_id),
                input_mode: 'unit',
                unit_price: material.default_unit_price,
            });
        }

        setPendingLineKey(null);
    };

    /* ---------------- Записване ---------------- */

    const canSubmit = serviceName.trim() !== '' && result.ok && !processing;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!canSubmit) {
            if (!result.ok) {
                toast.error(result.message);
            }

            return;
        }

        setProcessing(true);

        router.post(
            route('calculations.store'),
            {
                service_name: serviceName.trim(),
                client_name: clientName.trim() || null,
                notes: notes.trim() || null,
                calculated_at: calculatedAt,
                labor_cost: laborCost || '0',
                other_costs: otherCosts || '0',
                target_net_margin_percent: marginPercent || '0',
                final_price_override: priceOverride,
                idempotency_key: idempotencyKey.current,
                items: usableLines.map((line) => ({
                    material_id: line.material_id,
                    material_name: line.material_name.trim(),
                    sku: line.sku,
                    unit_id: Number(line.unit_id),
                    quantity: line.quantity,
                    input_mode: line.input_mode,
                    entered_total_cost: line.input_mode === 'total' ? line.entered_total_cost || '0' : null,
                    unit_price: line.input_mode === 'unit' ? line.unit_price || '0' : null,
                })),
            },
            {
                onSuccess: () => {
                    localStorage.removeItem(DRAFT_KEY);
                },
                onError: () => {
                    toast.error('Калкулацията не беше записана. Проверете отбелязаните полета.');
                    // Нов ключ, за да може потребителят да опита пак след корекция.
                    idempotencyKey.current = crypto.randomUUID();
                    setProcessing(false);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const copyPrice = async () => {
        if (!result.ok) {
            return;
        }

        try {
            await navigator.clipboard.writeText(result.finalPrice.toFixed(2));
            toast.success(`Цената ${formatMoney(result.finalPrice, currency)} е копирана.`);
        } catch {
            toast.error('Копирането не е разрешено от браузъра.');
        }
    };

    const summaryValues = result.ok
        ? {
              materialCostTotal: result.materialCostTotal.toFixed(2),
              laborCost: result.laborCost.toFixed(2),
              otherCosts: result.otherCosts.toFixed(2),
              totalCost: result.totalCost.toFixed(2),
              profitBeforeTax: result.profitBeforeTax.toFixed(2),
              corporateTaxAmount: result.corporateTaxAmount.toFixed(2),
              corporateRate: taxes.corporate,
              profitAfterCorporateTax: result.profitAfterCorporateTax.toFixed(2),
              dividendTaxAmount: result.dividendTaxAmount.toFixed(2),
              dividendRate: taxes.dividend,
              vatRate: taxes.vat,
              netProfit: result.netProfit.toFixed(2),
              finalPrice: result.finalPrice.toFixed(2),
              actualNetMargin: result.actualNetMargin.toFixed(6),
          }
        : null;

    return (
        <AppLayout
            title={source ? 'Копие на калкулация' : 'Нова калкулация'}
            description={source ? `По образец на ${source.calculation_number}` : undefined}
            actions={
                <Button type="submit" form="calculation-form" disabled={!canSubmit} className="hidden md:inline-flex">
                    {processing ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Save aria-hidden="true" />}
                    Запази
                </Button>
            }
        >
            <Head title={source ? 'Копие на калкулация' : 'Нова калкулация'} />

            <form id="calculation-form" onSubmit={submit} className="mx-auto grid max-w-6xl gap-4 lg:grid-cols-5 lg:items-start">
                <div className="space-y-4 lg:col-span-3">
                    {draftOffer && !draftRestored && (
                        <Alert>
                            <Sparkles className="size-4" aria-hidden="true" />
                            <AlertDescription className="flex flex-wrap items-center justify-between gap-2">
                                <span>Имате незавършена калкулация от този браузър.</span>
                                <span className="flex gap-2">
                                    <Button type="button" size="sm" variant="secondary" onClick={restoreDraft}>
                                        Възстанови
                                    </Button>
                                    <Button type="button" size="sm" variant="ghost" onClick={discardDraft}>
                                        Откажи
                                    </Button>
                                </span>
                            </AlertDescription>
                        </Alert>
                    )}

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Услуга</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="service_name">Име на услугата *</Label>
                                <Input
                                    id="service_name"
                                    value={serviceName}
                                    onChange={(e) => setServiceName(e.target.value)}
                                    placeholder="напр. Балонена арка"
                                    className="h-11 text-base"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.service_name} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="client_name">Клиент</Label>
                                    <Input
                                        id="client_name"
                                        value={clientName}
                                        onChange={(e) => setClientName(e.target.value)}
                                        placeholder="по желание"
                                        className="h-11"
                                    />
                                    <InputError message={errors.client_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="calculated_at">Дата</Label>
                                    <Input
                                        id="calculated_at"
                                        type="date"
                                        value={calculatedAt}
                                        onChange={(e) => setCalculatedAt(e.target.value)}
                                        className="h-11"
                                        required
                                    />
                                    <InputError message={errors.calculated_at} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Бележка</Label>
                                <Textarea
                                    id="notes"
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    rows={2}
                                    placeholder="по желание"
                                />
                                <InputError message={errors.notes} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                            <CardTitle className="text-base">Материали</CardTitle>
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {formatMoney(result.materialCostTotal, currency)}
                            </span>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {lines.length === 0 ? (
                                <EmptyState
                                    icon={Package}
                                    title="Няма добавени материали"
                                    description="Калкулацията може да е само с труд, но обикновено започва с материали."
                                />
                            ) : (
                                <ul ref={listRef} className="space-y-3">
                                    {lines.map((line, index) => (
                                        <MaterialRow
                                            key={line.key}
                                            line={line}
                                            index={index}
                                            units={unitList}
                                            materials={catalog}
                                            computed={rowResults[index]}
                                            currency={currency}
                                            canRemove={lines.length > 1}
                                            counted={usableLines.includes(line)}
                                            onChange={(patch) => updateLine(line.key, patch)}
                                            onRemove={() => removeLine(line.key)}
                                            onCreateMaterial={(name) => openMaterialDialog(line.key, name)}
                                        />
                                    ))}
                                </ul>
                            )}

                            <Button type="button" variant="outline" onClick={addLine} className="h-11 w-full">
                                <Plus aria-hidden="true" /> Добави материал
                            </Button>

                            <InputError message={errors.items} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Разходи и марж</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="labor_cost">Труд</Label>
                                    <NumericInput
                                        id="labor_cost"
                                        value={laborCost}
                                        onValueChange={setLaborCost}
                                        suffix={currency}
                                        placeholder="0,00"
                                        className="h-11"
                                    />
                                    <InputError message={errors.labor_cost} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="other_costs">Други разходи</Label>
                                    <NumericInput
                                        id="other_costs"
                                        value={otherCosts}
                                        onValueChange={setOtherCosts}
                                        suffix={currency}
                                        placeholder="0,00"
                                        className="h-11"
                                    />
                                    <InputError message={errors.other_costs} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="margin">Желан чист марж от крайната продажна цена</Label>
                                <div className="flex gap-2">
                                    <NumericInput
                                        id="margin"
                                        value={marginPercent}
                                        onValueChange={(value) => {
                                            setMarginPercent(value);
                                            setPriceOverride(null);
                                        }}
                                        suffix="%"
                                        className="h-11 w-28"
                                    />
                                    <div className="flex flex-1 gap-1.5">
                                        {MARGIN_PRESETS.map((preset) => (
                                            <Button
                                                key={preset}
                                                type="button"
                                                variant={marginPercent === preset ? 'default' : 'outline'}
                                                className="h-11 flex-1 px-0"
                                                onClick={() => {
                                                    setMarginPercent(preset);
                                                    setPriceOverride(null);
                                                }}
                                            >
                                                {preset}%
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Това е чистата печалба след корпоративен данък и данък дивидент, като процент от крайната цена — не надценка
                                    върху разходите.
                                </p>
                                <InputError message={errors.target_net_margin_percent} />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Обобщение: вдясно на широк екран, под формата на телефон. */}
                <div className="lg:sticky lg:top-20 lg:col-span-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Финансово обобщение</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {result.ok && summaryValues ? (
                                <FinancialSummary
                                    values={summaryValues}
                                    currency={currency}
                                    actions={
                                        <>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => setRoundingOpen(true)}
                                                disabled={!base.ok}
                                            >
                                                Закръгли цената
                                            </Button>
                                            <Button type="button" size="sm" variant="secondary" onClick={copyPrice}>
                                                <Copy aria-hidden="true" /> Копирай
                                            </Button>
                                        </>
                                    }
                                />
                            ) : (
                                <Alert variant={result.totalCost.eq(0) ? 'default' : 'destructive'}>
                                    <AlertCircle className="size-4" aria-hidden="true" />
                                    <AlertDescription>{result.ok ? '' : result.message}</AlertDescription>
                                </Alert>
                            )}

                            {priceOverride && result.ok && (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Цената е закръглена ръчно. Изчислената цена при {formatPercent(new Big(marginPercent || '0').div(100))} марж е{' '}
                                    {base.ok ? formatMoney(base.finalPrice, currency) : '—'}.{' '}
                                    <button type="button" className="underline" onClick={() => setPriceOverride(null)}>
                                        Върни я
                                    </button>
                                </p>
                            )}

                            <Button type="submit" disabled={!canSubmit} className="mt-4 hidden h-12 w-full text-base md:inline-flex">
                                {processing ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Save aria-hidden="true" />}
                                Запази калкулацията
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {/* Място за мобилната лента за записване. */}
                <div className="h-16 md:hidden" aria-hidden="true" />
            </form>

            {/* Мобилна лента за записване над долната навигация. */}
            <div
                className={cn(
                    'fixed inset-x-0 bottom-14 z-30 border-t bg-background/95 px-4 py-2.5 backdrop-blur md:hidden print:hidden',
                    'pb-[calc(0.625rem+env(safe-area-inset-bottom))]',
                )}
            >
                <div className="flex items-center gap-3">
                    <div className="min-w-0 flex-1">
                        <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Крайна цена</p>
                        <p className="truncate text-lg font-bold tabular-nums">
                            {result.ok ? formatMoney(result.finalPrice, currency) : '—'}
                        </p>
                    </div>
                    <Button type="submit" form="calculation-form" disabled={!canSubmit} className="h-11 px-5">
                        {processing ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Save aria-hidden="true" />}
                        Запази
                    </Button>
                </div>
            </div>

            {base.ok && (
                <RoundingDialog
                    open={roundingOpen}
                    onOpenChange={setRoundingOpen}
                    basePrice={base.finalPrice}
                    baseNetProfit={base.netProfit}
                    baseMargin={base.actualNetMargin}
                    input={breakdownInput}
                    currency={currency}
                    onApply={setPriceOverride}
                    isApplied={priceOverride !== null}
                />
            )}

            <MaterialFormDialog
                open={materialDialogOpen}
                onOpenChange={setMaterialDialogOpen}
                units={unitList}
                initialName={materialDialogName}
                onCreated={handleMaterialCreated}
            />
        </AppLayout>
    );
}
