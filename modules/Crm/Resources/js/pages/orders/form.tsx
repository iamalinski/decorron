import InputError from '@/components/input-error';
import { NumericInput } from '@/components/numeric-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { big, formatMoney } from '@/lib/money';
import type { Collection, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import Big from 'big.js';
import { ArrowLeft, Loader2, Plus, Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { CustomerPicker } from '../../components/customer-picker';
import type { CrmOptions, Customer, Order, Quote } from '../../types';

interface OrderFormProps {
    order: { data: Order } | null;
    presetQuote: { data: Quote } | null;
    presetCustomer: { data: Customer } | null;
    customers: Collection<Customer>;
    options: CrmOptions;
}

interface ItemRow {
    key: string;
    description: string;
    details: string;
    quantity: string;
    unit_price: string;
}

let rowCounter = 0;

function newRow(patch: Partial<ItemRow> = {}): ItemRow {
    rowCounter += 1;

    return { key: `row-${rowCounter}`, description: '', details: '', quantity: '1', unit_price: '', ...patch };
}

export default function OrderForm({ order: wrapper, presetQuote, presetCustomer, customers }: OrderFormProps) {
    const page = usePage<SharedData>();
    const errors = page.props.errors as Record<string, string>;
    const currency = page.props.app.currency;

    const existing = wrapper?.data ?? null;
    const quote = presetQuote?.data ?? null;
    const isEditing = existing !== null;

    const [customer, setCustomer] = useState<Customer | null>(presetCustomer?.data ?? null);

    const [eventDate, setEventDate] = useState(existing?.event_date ?? quote?.event_date ?? '');
    const [eventLocation, setEventLocation] = useState(existing?.event_location ?? quote?.event_location ?? '');
    const [depositAmount, setDepositAmount] = useState(existing?.deposit_amount ?? quote?.deposit_amount ?? '');
    const [executionNotes, setExecutionNotes] = useState(existing?.execution_notes ?? '');
    const [deadlineNote, setDeadlineNote] = useState(existing?.deadline_note ?? '');

    const [rows, setRows] = useState<ItemRow[]>(() => {
        const source = existing?.items ?? quote?.items ?? [];

        return source.length
            ? source.map((item) =>
                  newRow({
                      description: item.description,
                      details: item.details ?? '',
                      quantity: new Big(item.quantity).toString(),
                      unit_price: item.unit_price,
                  }),
              )
            : [newRow()];
    });

    const [processing, setProcessing] = useState(false);

    const usableRows = useMemo(
        () => rows.filter((row) => row.description.trim() !== '' && Number(row.quantity) > 0),
        [rows],
    );

    const total = useMemo(
        () => usableRows.reduce((sum, row) => sum.plus(big(row.quantity).times(big(row.unit_price)).round(2, Big.roundHalfUp)), new Big(0)),
        [usableRows],
    );

    const update = (key: string, patch: Partial<ItemRow>) => {
        setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (customer === null || usableRows.length === 0) {
            return;
        }

        setProcessing(true);

        const payload = {
            customer_id: customer.id,
            crm_opportunity_id: existing?.crm_opportunity_id ?? quote?.crm_opportunity_id ?? null,
            quote_id: existing?.quote_id ?? quote?.id ?? null,
            quote_version: existing?.quote_version ?? quote?.version ?? null,
            event_date: eventDate || null,
            event_location: eventLocation.trim() || null,
            deposit_amount: depositAmount || null,
            execution_notes: executionNotes.trim() || null,
            deadline_note: deadlineNote.trim() || null,
            items: usableRows.map((row) => ({
                description: row.description.trim(),
                details: row.details.trim() || null,
                quantity: row.quantity,
                unit_price: row.unit_price || '0',
            })),
        };

        const done = { onFinish: () => setProcessing(false), preserveScroll: true };

        if (existing) {
            router.patch(route('crm.orders.update', existing.id), payload, done);
        } else {
            router.post(route('crm.orders.store'), payload, done);
        }
    };

    return (
        <AppLayout
            title={isEditing ? `Редакция на ${existing.order_number}` : 'Нова поръчка'}
            description={customer?.name ?? 'Изберете клиент'}
        >
            <Head title={isEditing ? 'Редакция на поръчка' : 'Нова поръчка'} />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-4">
                <Button asChild variant="ghost" size="sm" className="-ml-2" type="button">
                    <Link href={isEditing ? route('crm.orders.show', existing.id) : route('crm.orders.index')}>
                        <ArrowLeft aria-hidden="true" /> Назад
                    </Link>
                </Button>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Клиент и събитие</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="order-customer">Клиент</Label>
                            {isEditing || quote ? (
                                <Input id="order-customer" value={customer?.name ?? ''} readOnly className="h-11" />
                            ) : (
                                <CustomerPicker id="order-customer" customers={customers.data} value={customer} onSelect={setCustomer} />
                            )}
                            <InputError message={errors.customer_id} />
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
                        <CardTitle className="text-base">Договорени услуги</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <ul className="grid gap-3">
                            {rows.map((row, index) => (
                                <li key={row.key} className="grid gap-2 rounded-lg border p-3">
                                    <div className="flex items-start gap-2">
                                        <div className="grid min-w-0 flex-1 gap-1.5">
                                            <Label htmlFor={`desc-${row.key}`} className="text-xs">
                                                Описание
                                            </Label>
                                            <Input
                                                id={`desc-${row.key}`}
                                                value={row.description}
                                                onChange={(event) => update(row.key, { description: event.target.value })}
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
                                </li>
                            ))}
                        </ul>

                        <div>
                            <Button type="button" variant="outline" onClick={() => setRows((current) => [...current, newRow()])}>
                                <Plus aria-hidden="true" /> Нов ред
                            </Button>
                        </div>

                        <InputError message={errors.items} />

                        <div className="flex items-baseline justify-between border-t pt-3">
                            <span className="font-medium">Договорена сума</span>
                            <span className="text-2xl font-bold tabular-nums">{formatMoney(total, currency)}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Изпълнение</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-4 sm:grid-cols-2">
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

                            <div className="grid gap-1.5">
                                <Label htmlFor="deadline_note">Срокове</Label>
                                <Input
                                    id="deadline_note"
                                    value={deadlineNote}
                                    onChange={(event) => setDeadlineNote(event.target.value)}
                                    placeholder="напр. Монтаж от 08:00"
                                    className="h-11"
                                    maxLength={255}
                                />
                                <InputError message={errors.deadline_note} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="execution_notes">Бележки за изпълнението</Label>
                            <Textarea
                                id="execution_notes"
                                value={executionNotes}
                                onChange={(event) => setExecutionNotes(event.target.value)}
                                rows={4}
                                maxLength={5000}
                            />
                            <InputError message={errors.execution_notes} />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end pb-4">
                    <Button type="submit" size="lg" disabled={processing || customer === null || usableRows.length === 0}>
                        {processing ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Save aria-hidden="true" />}
                        {isEditing ? 'Запази' : 'Създай поръчката'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
