import InputError from '@/components/input-error';
import { TrashActions, TrashBanner } from '@/components/trash-actions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney } from '@/lib/money';
import type { Collection } from '@/types';
import type { Calculation } from '@/types/decorron';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Bot, Calculator, CheckCircle2, FileText, Hand, Pencil, Phone, Plus, RotateCcw, Send, XCircle } from 'lucide-react';
import { useState } from 'react';
import { OpportunityFormDialog } from '../../components/opportunity-form-dialog';
import { QuoteStatusBadge } from '../../components/quote-status-badge';
import { StageBadge, StatusBadge } from '../../components/stage-badge';
import type { CrmOptions, Customer, Opportunity, Order, Quote } from '../../types';

interface OpportunityShowProps {
    opportunity: { data: Opportunity };
    customer: { data: Customer };
    calculations: Collection<Calculation>;
    quotes: Collection<Quote>;
    orders: Collection<Order>;
    options: CrmOptions;
}

export default function OpportunityShow({
    opportunity: wrapper,
    customer: customerWrapper,
    calculations,
    quotes,
    orders,
    options,
}: OpportunityShowProps) {
    const opportunity = wrapper.data;
    const customer = customerWrapper.data;

    const [editing, setEditing] = useState(false);
    const [closing, setClosing] = useState<'won' | 'lost' | null>(null);
    const [note, setNote] = useState('');
    const [noteError, setNoteError] = useState<string | undefined>();
    const [savingNote, setSavingNote] = useState(false);

    const isTrashed = Boolean(opportunity.deleted_at);
    const isOpen = opportunity.status === 'open' && !isTrashed;

    const changeStage = (stage: string) => {
        router.patch(route('crm.opportunities.stage', opportunity.id), { stage }, { preserveScroll: true });
    };

    const reopen = () => {
        router.patch(route('crm.opportunities.close', opportunity.id), { status: 'open' }, { preserveScroll: true });
    };

    const addNote = (event: React.FormEvent) => {
        event.preventDefault();
        setSavingNote(true);
        setNoteError(undefined);

        router.post(
            route('crm.opportunities.notes.store', opportunity.id),
            { description: note },
            {
                preserveScroll: true,
                onSuccess: () => setNote(''),
                onError: (bag) => setNoteError(bag.description),
                onFinish: () => setSavingNote(false),
            },
        );
    };

    return (
        <AppLayout
            title={opportunity.title}
            description={customer.name}
            actions={
                <>
                    {!isTrashed && (
                        <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                            <Pencil aria-hidden="true" /> <span className="hidden sm:inline">Редакция</span>
                        </Button>
                    )}
                    <TrashActions
                        deletedAt={opportunity.deleted_at}
                        blockers={opportunity.deletion_blockers}
                        name={`Запитване „${opportunity.title}“`}
                        subject="запитването"
                        destroyUrl={route('crm.opportunities.destroy', opportunity.id)}
                        restoreUrl={route('crm.opportunities.restore', opportunity.id)}
                        forceUrl={route('crm.opportunities.force-destroy', opportunity.id)}
                        alsoRemoved="Хронологията на запитването ще го последва."
                    />
                </>
            }
        >
            <Head title={opportunity.title} />

            <div className="mx-auto max-w-3xl space-y-4">
                <Button asChild variant="ghost" size="sm" className="-ml-2">
                    <Link href={route('crm.opportunities.index')}>
                        <ArrowLeft aria-hidden="true" /> Към запитванията
                    </Link>
                </Button>

                {isTrashed && (
                    <TrashBanner>
                        Това запитване е в Кошчето. То не участва във фунията, таблото и календара, докато не бъде
                        върнато.
                    </TrashBanner>
                )}

                <Card>
                    <CardHeader className="gap-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <StageBadge stage={opportunity.stage} label={opportunity.stage_label} />
                            <StatusBadge status={opportunity.status} label={opportunity.status_label} />
                        </div>
                        <CardTitle className="text-base">Етап и статус</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {isOpen ? (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="stage-select">Премести на етап</Label>
                                    <Select value={opportunity.stage} onValueChange={changeStage}>
                                        <SelectTrigger id="stage-select" className="h-11">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.stages.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-muted-foreground text-xs">
                                        Етапът се сменя само когато вие го промените — създаването на документ не го мести автоматично.
                                    </p>
                                </div>

                                {orders.data.length > 0 && (
                                    <p className="text-muted-foreground text-xs">
                                        По това запитване вече има поръчка. Ако работата е договорена, отбележете го като спечелено —
                                        системата не го прави сама.
                                    </p>
                                )}

                                <div className="flex flex-wrap gap-2">
                                    <Button type="button" variant="outline" onClick={() => setClosing('won')}>
                                        <CheckCircle2 aria-hidden="true" /> Спечелена
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => setClosing('lost')}>
                                        <XCircle aria-hidden="true" /> Загубена
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <div className="space-y-2">
                                <p className="text-muted-foreground text-sm">
                                    Запитването е приключено като „{opportunity.status_label}“
                                    {opportunity.lost_reason_label ? ` — ${opportunity.lost_reason_label}` : ''}.
                                    {opportunity.lost_reason_note && ` ${opportunity.lost_reason_note}`}
                                </p>
                                <Button type="button" variant="outline" onClick={reopen}>
                                    <RotateCcw aria-hidden="true" /> Върни в работа
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Детайли за събитието</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                        <Field label="Вид събитие">{opportunity.event_type ?? '—'}</Field>
                        <Field label="Дата">{opportunity.event_date ? formatDate(opportunity.event_date) : 'Не е уточнена'}</Field>
                        <Field label="Място">{opportunity.event_location ?? '—'}</Field>
                        <Field label="Очакван бюджет">
                            {opportunity.expected_budget ? formatMoney(opportunity.expected_budget) : '—'}
                        </Field>
                        <Field label="Източник">{opportunity.source_label ?? '—'}</Field>
                        <Field label="Следващо действие">
                            {opportunity.next_action ?? '—'}
                            {opportunity.next_action_due && (
                                <span className="text-muted-foreground block text-xs">
                                    до {formatDate(opportunity.next_action_due)}
                                </span>
                            )}
                        </Field>
                        {opportunity.notes && (
                            <div className="sm:col-span-2">
                                <p className="text-muted-foreground text-xs">Вътрешни бележки</p>
                                <p className="mt-0.5 whitespace-pre-wrap">{opportunity.notes}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Клиент</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <Link href={route('crm.customers.show', customer.id)} className="font-medium hover:underline">
                                {customer.name}
                            </Link>
                            <p className="text-muted-foreground text-sm">
                                {[customer.phone, customer.email].filter(Boolean).join(' · ') || 'Без контакти'}
                            </p>
                        </div>
                        {customer.phone && (
                            <Button asChild variant="outline" size="sm">
                                <a href={`tel:${customer.phone}`}>
                                    <Phone aria-hidden="true" /> Обаждане
                                </a>
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle className="text-base">Калкулации</CardTitle>
                        <Button asChild size="sm" variant="outline">
                            <Link href={`${route('calculations.create')}?opportunity=${opportunity.id}`}>
                                <Plus aria-hidden="true" /> Нова калкулация
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {calculations.data.length === 0 ? (
                            <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                <Calculator className="size-4" aria-hidden="true" />
                                Още няма калкулации по това запитване.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {calculations.data.map((calculation) => (
                                    <li key={calculation.id}>
                                        <Link
                                            href={route('calculations.show', calculation.id)}
                                            className="hover:bg-accent/40 focus-visible:ring-ring flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{calculation.service_name}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {calculation.calculation_number} · {formatDate(calculation.calculated_at)}
                                                </p>
                                            </div>
                                            <span className="shrink-0 font-semibold tabular-nums">
                                                {formatMoney(calculation.final_price, calculation.currency)}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="text-muted-foreground mt-3 text-xs">
                            Показаните стойности са тези от момента на записване и не се преизчисляват.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle className="text-base">Оферти</CardTitle>
                        <Button asChild size="sm" variant="outline">
                            <Link href={`${route('crm.quotes.create')}?opportunity=${opportunity.id}`}>
                                <Plus aria-hidden="true" /> Нова оферта
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {quotes.data.length === 0 ? (
                            <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                <FileText className="size-4" aria-hidden="true" />
                                Още няма оферти по това запитване.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {quotes.data.map((quote) => (
                                    <li key={quote.id}>
                                        <Link
                                            href={route('crm.quotes.show', quote.id)}
                                            className="hover:bg-accent/40 focus-visible:ring-ring flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{quote.quote_number}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {formatDate(quote.issued_at)} · версия {quote.version}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <QuoteStatusBadge status={quote.status} label={quote.status_label} />
                                                <span className="font-semibold tabular-nums">
                                                    {formatMoney(quote.total, quote.currency)}
                                                </span>
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {orders.data.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Поръчки</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-2">
                                {orders.data.map((order) => (
                                    <li key={order.id}>
                                        <Link
                                            href={route('crm.orders.show', order.id)}
                                            className="hover:bg-accent/40 focus-visible:ring-ring flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{order.order_number}</p>
                                                <p className="text-muted-foreground text-xs">{order.status_label}</p>
                                            </div>
                                            <span className="shrink-0 font-semibold tabular-nums">
                                                {formatMoney(order.agreed_total, order.currency)}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Хронология</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form onSubmit={addNote} className="grid gap-2">
                            <Label htmlFor="activity-note" className="sr-only">
                                Нова бележка
                            </Label>
                            <Textarea
                                id="activity-note"
                                value={note}
                                onChange={(event) => setNote(event.target.value)}
                                placeholder="Какво се случи? Напр. „Говорихме по телефона, иска бели и златни балони.“"
                                rows={2}
                                maxLength={500}
                            />
                            <InputError message={noteError} />
                            <div>
                                <Button type="submit" size="sm" disabled={savingNote || note.trim() === ''}>
                                    <Send aria-hidden="true" /> Добави бележка
                                </Button>
                            </div>
                        </form>

                        {(opportunity.activities ?? []).length === 0 ? (
                            <p className="text-muted-foreground text-sm">Още няма записани събития.</p>
                        ) : (
                            <ol className="space-y-3">
                                {(opportunity.activities ?? []).map((activity) => (
                                    <li key={activity.id} className="flex gap-3 text-sm">
                                        <span
                                            className="text-muted-foreground mt-0.5 shrink-0"
                                            title={activity.is_automatic ? 'Записано автоматично от системата' : 'Ръчно добавено'}
                                        >
                                            {activity.is_automatic ? (
                                                <Bot className="size-4" aria-hidden="true" />
                                            ) : (
                                                <Hand className="size-4" aria-hidden="true" />
                                            )}
                                            <span className="sr-only">
                                                {activity.is_automatic ? 'Автоматичен запис' : 'Ръчен запис'}
                                            </span>
                                        </span>
                                        <div className="min-w-0">
                                            <p className="break-words">{activity.description}</p>
                                            <p className="text-muted-foreground text-xs">{formatDateTime(activity.created_at)}</p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </CardContent>
                </Card>
            </div>

            <OpportunityFormDialog
                open={editing}
                onOpenChange={setEditing}
                options={options}
                customers={[customer]}
                opportunity={opportunity}
                lockedCustomer={customer}
            />

            <CloseDialog
                mode={closing}
                onClose={() => setClosing(null)}
                opportunityId={opportunity.id}
                lostReasons={options.lost_reasons}
            />
        </AppLayout>
    );
}

function CloseDialog({
    mode,
    onClose,
    opportunityId,
    lostReasons,
}: {
    mode: 'won' | 'lost' | null;
    onClose: () => void;
    opportunityId: number;
    lostReasons: { value: string; label: string }[];
}) {
    const [reason, setReason] = useState('price');
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        setProcessing(true);

        router.patch(
            route('crm.opportunities.close', opportunityId),
            mode === 'lost' ? { status: 'lost', lost_reason: reason, lost_reason_note: note } : { status: 'won' },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={mode !== null} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{mode === 'won' ? 'Маркиране като спечелена' : 'Маркиране като загубена'}</DialogTitle>
                    <DialogDescription>
                        {mode === 'won'
                            ? 'Запитването се приключва като спечелено. Това не създава автоматично поръчка.'
                            : 'Посочете причината, ако я знаете — помага при следващи оферти.'}
                    </DialogDescription>
                </DialogHeader>

                {mode === 'lost' && (
                    <div className="grid gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="lost-reason">Причина</Label>
                            <Select value={reason} onValueChange={setReason}>
                                <SelectTrigger id="lost-reason" className="h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {lostReasons.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="lost-note">Пояснение (по избор)</Label>
                            <Textarea
                                id="lost-note"
                                value={note}
                                onChange={(event) => setNote(event.target.value)}
                                rows={2}
                                maxLength={255}
                            />
                        </div>
                    </div>
                )}

                <DialogFooter className="gap-2">
                    <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                        Отказ
                    </Button>
                    <Button type="button" onClick={confirm} disabled={processing}>
                        Потвърди
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="mt-0.5">{children}</p>
        </div>
    );
}

function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat('bg-BG', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(iso));
}
