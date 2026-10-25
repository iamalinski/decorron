import InputError from '@/components/input-error';
import { NumericInput } from '@/components/numeric-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { big, formatMoney, today } from '@/lib/money';
import type { Collection, SharedData } from '@/types';
import type { Calculation } from '@/types/decorron';
import { Head, Link, router, usePage } from '@inertiajs/react';
import Big from 'big.js';
import { ArrowLeft, Calculator, Info, Loader2, Plus, Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CustomerPicker } from '../../components/customer-picker';
import type { CrmOptions, Customer, Opportunity, Quote } from '../../types';

interface QuoteFormProps {
    quote: { data: Quote } | null;
    presetCustomer: { data: Customer } | null;
    presetOpportunity: { data: Opportunity } | null;
    calculations: Collection<Calculation>;
    customers: Collection<Customer>;
    defaults: { issued_at: string; valid_until: string; terms: string | null } | null;
    options: CrmOptions;
}

interface ItemRow {
    key: string;
    calculation_id: number | null;
    description: string;
    details: string;
    quantity: string;
    unit_price: string;
}

const NO_DISCOUNT = '__none__';

let rowCounter = 0;

function newRow(patch: Partial<ItemRow> = {}): ItemRow {
    rowCounter += 1;

    return {
        key: `row-${rowCounter}`,
        calculation_id: null,
        description: '',
        details: '',
        quantity: '1',
        unit_price: '',
        ...patch,
    };
}

export default function QuoteForm({
    quote: wrapper,
    presetCustomer,
    presetOpportunity,
    calculations,
    customers,
    defaults,
    options,
}: QuoteFormProps) {
    const page = usePage<SharedData>();
    const errors = page.props.errors as Record<string, string>;
    const currency = page.props.app.currency;

    const existing = wrapper?.data ?? null;
    const isEditing = existing !== null;

    const [customer, setCustomer] = useState<Customer | null>(presetCustomer?.data ?? null);
    const [opportunityId] = useState<number | null>(existing?.crm_opportunity_id ?? presetOpportunity?.data.id ?? null);

    const [issuedAt, setIssuedAt] = useState(existing?.issued_at ?? defaults?.issued_at ?? today());
    const [validUntil, setValidUntil] = useState(existing?.valid_until ?? defaults?.valid_until ?? '');
    const [eventDate, setEventDate] = useState(existing?.event_date ?? presetOpportunity?.data.event_date ?? '');
    const [eventLocation, setEventLocation] = useState(existing?.event_location ?? presetOpportunity?.data.event_location ?? '');

    const [discountType, setDiscountType] = useState(existing?.discount_type ?? '');
    const [discountValue, setDiscountValue] = useState(existing?.discount_value ?? '');
    const [depositAmount, setDepositAmount] = useState(existing?.deposit_amount ?? '');
    const [terms, setTerms] = useState(existing?.terms ?? defaults?.terms ?? '');
    const [internalNotes, setInternalNotes] = useState(existing?.internal_notes ?? '');

    const [rows, setRows] = useState<ItemRow[]>(() =>
        existing?.items?.length
            ? existing.items.map((item) =>
                  newRow({
                      calculation_id: item.calculation_id,
                      description: item.description,
                      details: item.details ?? '',
                      quantity: new Big(item.quantity).toString(),
                      unit_price: item.unit_price,
                  }),
              )
            : [newRow()],
    );

    const [processing, setProcessing] = useState(false);

    const usableRows = useMemo(
        () => rows.filter((row) => row.description.trim() !== '' && Number(row.quantity) > 0),
        [rows],
    );

    const subtotal = useMemo(
        () => usableRows.reduce((sum, row) => sum.plus(big(row.quantity).times(big(row.unit_price)).round(2, Big.roundHalfUp)), new Big(0)),
        [usableRows],
    );

    const discount = useMemo(() => {
        if (discountType === '' || discountValue === '') {
            return new Big(0);
        }

        const amount =
            discountType === 'percent'
                ? subtotal.times(big(discountValue)).div(100).round(2, Big.roundHalfUp)
                : big(discountValue).round(2, Big.roundHalfUp);

        if (amount.gt(subtotal)) {
            return subtotal;
        }

        return amount.lt(0) ? new Big(0) : amount;
    }, [discountType, discountValue, subtotal]);

    const total = subtotal.minus(discount);

    const update = (key: string, patch: Partial<ItemRow>) => {
        setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
    };

    const addFromCalculation = (calculation: Calculation) => {
        setRows((current) => {
            const next = [
                ...current.filter((row) => row.description.trim() !== ''),
                newRow({
                    calculation_id: calculation.id,
                    description: calculation.service_name,
                    quantity: '1',
                    unit_price: calculation.final_price,
                }),
            ];

            return next.length === 0 ? [newRow()] : next;
        });
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (usableRows.length === 0 || customer === null) {
            return;
        }

        setProcessing(true);

        const payload = {
            customer_id: customer.id,
            crm_opportunity_id: opportunityId,
            issued_at: issuedAt,
            valid_until: validUntil || null,
            event_date: eventDate || null,
            event_location: eventLocation.trim() || null,
            discount_type: discountType || null,
            discount_value: discountType === '' ? null : discountValue || '0',
            deposit_amount: depositAmount || null,
            terms: terms.trim() || null,
            internal_notes: internalNotes.trim() || null,
            items: usableRows.map((row) => ({
                calculation_id: row.calculation_id,
                description: row.description.trim(),
                details: row.details.trim() || null,
                quantity: row.quantity,
                unit_price: row.unit_price || '0',
            })),
        };

        const done = { onFinish: () => setProcessing(false), preserveScroll: true };

        if (existing) {
            router.patch(route('crm.quotes.update', existing.id), payload, done);
        } else {
            router.post(route('crm.quotes.store'), payload, done);
        }
    };

    return (
        <AppLayout
            title={isEditing ? `Редакция на ${existing.quote_number}` : 'Нова оферта'}
            description={customer?.name ?? 'Изберете клиент'}
        >
            <Head title={isEditing ? 'Редакция на оферта' : 'Нова оферта'} />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-4">
                <Button asChild variant="ghost" size="sm" className="-ml-2" type="button">
                    <Link href={isEditing ? route('crm.quotes.show', existing.id) : route('crm.quotes.index')}>
                        <ArrowLeft aria-hidden="true" /> Назад
                    </Link>
                </Button>

                {isEditing && existing.was_sent && (
                    <Alert>
                        <Info aria-hidden="true" />
                        <AlertDescription>
                            Тази оферта вече е изпратена. Запазването ще създаде версия {existing.version + 1}; изпратената версия{' '}
                            {existing.version} остава непроменена.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Клиент и срокове</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="quote-customer">Клиент</Label>
                            {isEditing ? (
                                <Input id="quote-customer" value={existing.customer_name} readOnly className="h-11" />
                            ) : (
                                <CustomerPicker id="quote-customer" customers={customers.data} value={customer} onSelect={setCustomer} />
                            )}
                            <InputError message={errors.customer_id} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="issued_at">Дата на офертата</Label>
                                <Input
                                    id="issued_at"
                                    type="date"
                                    value={issuedAt}
                                    onChange={(event) => setIssuedAt(event.target.value)}
                                    className="h-11"
                                    required
                                />
                                <InputError message={errors.issued_at} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="valid_until">Валидна до</Label>
                                <Input
                                    id="valid_until"
                                    type="date"
                                    value={validUntil}
                                    onChange={(event) => setValidUntil(event.target.value)}
                                    className="h-11"
                                />
                                <InputError message={errors.valid_until} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="event_date">Дата на събитието</Label>
                                <Input
                                    id="event_date"
                                    type="date"
                                    value={eventDate}
                                    onChange={(event) => setEventDate(event.target.value)}
                                    className="h-11"
                                />
                                <InputError message={errors.event_date} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="event_location">Място</Label>
                                <Input
                                    id="event_location"
                                    value={eventLocation}
                                    onChange={(event) => setEventLocation(event.target.value)}
                                    className="h-11"
                                    maxLength={255}
                                />
                                <InputError message={errors.event_location} />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Услуги в офертата</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {calculations.data.length > 0 && (
                            <div className="grid gap-1.5">
                                <Label className="text-muted-foreground text-xs">Добави от калкулация</Label>
                                <div className="flex flex-wrap gap-2">
                                    {calculations.data.map((calculation) => (
                                        <Button
                                            key={calculation.id}
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => addFromCalculation(calculation)}
                                        >
                                            <Calculator aria-hidden="true" />
                                            {calculation.service_name} · {formatMoney(calculation.final_price, calculation.currency)}
                                        </Button>
                                    ))}
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Цената се копира в офертата. Промяната ѝ тук не променя калкулацията.
                                </p>
                            </div>
                        )}

                        <ul className="grid gap-3">
                            {rows.map((row, index) => (
                                <li key={row.key} className="grid gap-2 rounded-lg border p-3">
                                    <div className="flex items-start gap-2">
                                        <div className="min-w-0 flex-1 grid gap-1.5">
                                            <Label htmlFor={`desc-${row.key}`} className="text-xs">
                                                Описание за клиента
                                            </Label>
                                            <Input
                                                id={`desc-${row.key}`}
                                                value={row.description}
                                                onChange={(event) => update(row.key, { description: event.target.value })}
                                                placeholder="напр. Балонена арка в бяло и злато"
                                                className="h-11"
                                                maxLength={255}
                                            />
                                        </div>
                                        {rows.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="mt-6 h-11 w-11 shrink-0"
                                                onClick={() => setRows((current) => current.filter((item) => item.key !== row.key))}
                                                aria-label={`Премахни ред ${index + 1}`}
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
                                    </div>

                                    <div className="grid gap-2 sm:grid-cols-3">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor={`qty-${row.key}`} className="text-xs">
                                                Количество
                                            </Label>
                                            <NumericInput
                                                id={`qty-${row.key}`}
                                                value={row.quantity}
                                                onValueChange={(value) => update(row.key, { quantity: value })}
                                                className="h-11"
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor={`price-${row.key}`} className="text-xs">
                                                Единична цена
                                            </Label>
                                            <NumericInput
                                                id={`price-${row.key}`}
                                                value={row.unit_price}
                                                onValueChange={(value) => update(row.key, { unit_price: value })}
                                                suffix={currency}
                                                className="h-11"
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">Сума</Label>
                                            <p className="flex h-11 items-center font-medium tabular-nums">
                                                {formatMoney(big(row.quantity).times(big(row.unit_price)).round(2, Big.roundHalfUp), currency)}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor={`details-${row.key}`} className="text-xs">
                                            Допълнително описание (по избор)
                                        </Label>
                                        <Textarea
                                            id={`details-${row.key}`}
                                            value={row.details}
                                            onChange={(event) => update(row.key, { details: event.target.value })}
                                            rows={2}
                                            maxLength={2000}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <div>
                            <Button type="button" variant="outline" onClick={() => setRows((current) => [...current, newRow()])}>
                                <Plus aria-hidden="true" /> Нов ред
                            </Button>
                        </div>

                        <InputError message={errors.items} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Отстъпка, капаро и условия</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="discount_type">Вид отстъпка</Label>
                                <Select
                                    value={discountType === '' ? NO_DISCOUNT : discountType}
                                    onValueChange={(value) => setDiscountType(value === NO_DISCOUNT ? '' : value)}
                                >
                                    <SelectTrigger id="discount_type" className="h-11">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_DISCOUNT}>Без отстъпка</SelectItem>
                                        {options.discount_types.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {discountType !== '' && (
                                <div className="grid gap-1.5">
                                    <Label htmlFor="discount_value">Стойност</Label>
                                    <NumericInput
                                        id="discount_value"
                                        value={discountValue}
                                        onValueChange={setDiscountValue}
                                        suffix={discountType === 'percent' ? '%' : currency}
                                        className="h-11"
                                    />
                                    <InputError message={errors.discount_value} />
                                </div>
                            )}

                            <div className="grid gap-1.5">
                                <Label htmlFor="deposit_amount">Капаро</Label>
                                <NumericInput
                                    id="deposit_amount"
                                    value={depositAmount}
                                    onValueChange={setDepositAmount}
                                    suffix={currency}
                                    className="h-11"
                                />
                                <InputError message={errors.deposit_amount} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="terms">Условия (виждат се от клиента)</Label>
                            <Textarea
                                id="terms"
                                value={terms}
                                onChange={(event) => setTerms(event.target.value)}
                                rows={4}
                                maxLength={5000}
                                placeholder="Срокове, начин на плащане, условия за капаро…"
                            />
                            <InputError message={errors.terms} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="internal_notes">Вътрешни бележки (не се виждат от клиента)</Label>
                            <Textarea
                                id="internal_notes"
                                value={internalNotes}
                                onChange={(event) => setInternalNotes(event.target.value)}
                                rows={2}
                                maxLength={5000}
                            />
                            <InputError message={errors.internal_notes} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="grid gap-2 pt-6 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Междинна сума</span>
                            <span className="tabular-nums">{formatMoney(subtotal, currency)}</span>
                        </div>
                        {discount.gt(0) && (
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Отстъпка</span>
                                <span className="tabular-nums">−{formatMoney(discount, currency)}</span>
                            </div>
                        )}
                        <div className="flex items-baseline justify-between border-t pt-2">
                            <span className="font-medium">Общо за клиента</span>
                            <span className="text-2xl font-bold tabular-nums">{formatMoney(total, currency)}</span>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            Сумите се преизчисляват от сървъра при запазване. Себестойността и маржът не се показват на клиента.
                        </p>
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2 pb-4">
                    <Button type="submit" size="lg" disabled={processing || usableRows.length === 0 || customer === null}>
                        {processing ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Save aria-hidden="true" />}
                        {isEditing ? 'Запази' : 'Създай офертата'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
