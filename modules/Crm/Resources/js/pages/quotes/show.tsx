import { TrashActions, TrashBanner } from '@/components/trash-actions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney } from '@/lib/money';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, ClipboardList, Info, Pencil, Printer, Send, X } from 'lucide-react';
import { useState } from 'react';
import { QuoteStatusBadge } from '../../components/quote-status-badge';
import type { CrmOptions, Quote } from '../../types';

interface QuoteShowProps {
    quote: { data: Quote };
    options: CrmOptions;
}

export default function QuoteShow({ quote: wrapper }: QuoteShowProps) {
    const quote = wrapper.data;
    const [confirmSend, setConfirmSend] = useState(false);
    const isTrashed = Boolean(quote.deleted_at);

    const setStatus = (status: string) => {
        router.patch(route('crm.quotes.status', quote.id), { status }, { preserveScroll: true });
    };

    return (
        <AppLayout
            title={quote.quote_number}
            description={`${quote.customer_name} · версия ${quote.version}`}
            actions={
                <>
                    <Button asChild size="sm" variant="outline">
                        <Link href={route('crm.quotes.print', quote.id)}>
                            <Printer aria-hidden="true" /> <span className="hidden sm:inline">Печат / PDF</span>
                        </Link>
                    </Button>
                    {quote.is_editable && !isTrashed && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={route('crm.quotes.edit', quote.id)}>
                                <Pencil aria-hidden="true" /> <span className="hidden sm:inline">Редакция</span>
                            </Link>
                        </Button>
                    )}
                    <TrashActions
                        deletedAt={quote.deleted_at}
                        blockers={quote.deletion_blockers}
                        name={`Оферта ${quote.quote_number}`}
                        subject="офертата"
                        destroyUrl={route('crm.quotes.destroy', quote.id)}
                        restoreUrl={route('crm.quotes.restore', quote.id)}
                        forceUrl={route('crm.quotes.force-destroy', quote.id)}
                        alsoRemoved={
                            quote.was_sent
                                ? 'Офертата вече е изпращана на клиента — замразените ѝ версии я следват.'
                                : undefined
                        }
                    />
                </>
            }
        >
            <Head title={quote.quote_number} />

            <div className="mx-auto max-w-3xl space-y-4">
                <Button asChild variant="ghost" size="sm" className="-ml-2">
                    <Link href={route('crm.quotes.index')}>
                        <ArrowLeft aria-hidden="true" /> Към офертите
                    </Link>
                </Button>

                {isTrashed && (
                    <TrashBanner>
                        Тази оферта е в Кошчето. Тя не се брои в справките и не може да бъде изпращана или
                        приемана, докато не бъде върната.
                    </TrashBanner>
                )}

                <Card>
                    <CardHeader className="gap-2">
                        <div className="flex flex-wrap items-center gap-2">
                            <QuoteStatusBadge status={quote.status} label={quote.status_label} />
                            {quote.sent_at && (
                                <span className="text-muted-foreground text-xs">изпратена на {formatDate(quote.sent_at)}</span>
                            )}
                        </div>
                        <CardTitle className="text-base">Статус</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <Alert>
                            <Info aria-hidden="true" />
                            <AlertDescription>
                                Разпечатването или запазването като PDF не променя статуса. Офертата се отбелязва като изпратена само
                                когато вие потвърдите, че сте я изпратили.
                            </AlertDescription>
                        </Alert>

                        <div className="flex flex-wrap gap-2">
                            {quote.status === 'draft' && (
                                <Button type="button" variant="outline" onClick={() => setStatus('ready')}>
                                    Готова за изпращане
                                </Button>
                            )}

                            {quote.status !== 'accepted' && (
                                <Button type="button" onClick={() => setConfirmSend(true)}>
                                    <Send aria-hidden="true" /> Отбележи като изпратена
                                </Button>
                            )}

                            {quote.was_sent && quote.status !== 'accepted' && (
                                <>
                                    <Button type="button" variant="outline" onClick={() => setStatus('accepted')}>
                                        <Check aria-hidden="true" /> Приета от клиента
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => setStatus('declined')}>
                                        <X aria-hidden="true" /> Отказана
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => setStatus('expired')}>
                                        Изтекла
                                    </Button>
                                </>
                            )}

                        </div>

                        {quote.status === 'accepted' && (
                            <div className="space-y-3">
                                <Alert>
                                    <Check aria-hidden="true" />
                                    <AlertDescription>Приетата оферта вече не се редактира.</AlertDescription>
                                </Alert>
                                <Button
                                    type="button"
                                    onClick={() =>
                                        router.post(route('crm.quotes.order', quote.id), {}, { preserveScroll: true })
                                    }
                                >
                                    <ClipboardList aria-hidden="true" /> Създай поръчка от офертата
                                </Button>
                                <p className="text-muted-foreground text-xs">
                                    Повторното натискане не създава втора поръчка — отваря вече създадената.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Съдържание</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            <Field label="Клиент">{quote.customer_name}</Field>
                            <Field label="Издадена">{formatDate(quote.issued_at)}</Field>
                            <Field label="Валидна до">{quote.valid_until ? formatDate(quote.valid_until) : '—'}</Field>
                            <Field label="Събитие">
                                {quote.event_date ? formatDate(quote.event_date) : '—'}
                                {quote.event_location ? ` · ${quote.event_location}` : ''}
                            </Field>
                            {quote.crm_opportunity_id && (
                                <Field label="Запитване">
                                    <Link href={route('crm.opportunities.show', quote.crm_opportunity_id)} className="hover:underline">
                                        {quote.opportunity_title ?? 'Виж запитването'}
                                    </Link>
                                </Field>
                            )}
                        </dl>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Услуга</TableHead>
                                    <TableHead className="text-right">Кол.</TableHead>
                                    <TableHead className="text-right">Ед. цена</TableHead>
                                    <TableHead className="text-right">Сума</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {(quote.items ?? []).map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            {item.description}
                                            {item.details && <p className="text-muted-foreground text-xs">{item.details}</p>}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{Number(item.quantity)}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(item.unit_price, quote.currency)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(item.line_total, quote.currency)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="grid gap-1 text-sm">
                            <Row label="Междинна сума" value={formatMoney(quote.subtotal, quote.currency)} />
                            {Number(quote.discount_amount) > 0 && (
                                <Row label="Отстъпка" value={`−${formatMoney(quote.discount_amount, quote.currency)}`} />
                            )}
                            <div className="flex items-baseline justify-between border-t pt-2">
                                <span className="font-medium">Общо</span>
                                <span className="text-xl font-bold tabular-nums">{formatMoney(quote.total, quote.currency)}</span>
                            </div>
                            {quote.deposit_amount && (
                                <Row label="Капаро" value={formatMoney(quote.deposit_amount, quote.currency)} />
                            )}
                        </div>

                        {quote.terms && (
                            <div>
                                <p className="text-muted-foreground text-xs">Условия</p>
                                <p className="mt-0.5 text-sm whitespace-pre-wrap">{quote.terms}</p>
                            </div>
                        )}

                        {quote.internal_notes && (
                            <div>
                                <p className="text-muted-foreground text-xs">Вътрешни бележки (не се виждат от клиента)</p>
                                <p className="mt-0.5 text-sm whitespace-pre-wrap">{quote.internal_notes}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {(quote.versions ?? []).length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Изпратени версии</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-2 text-sm">
                                {(quote.versions ?? []).map((version) => (
                                    <li key={version.id} className="flex items-center justify-between gap-3 rounded-lg border p-3">
                                        <span>
                                            Версия {version.version}
                                            {version.sent_at && (
                                                <span className="text-muted-foreground"> · изпратена на {formatDate(version.sent_at)}</span>
                                            )}
                                        </span>
                                        <span className="font-medium tabular-nums">{formatMoney(version.total, quote.currency)}</span>
                                    </li>
                                ))}
                            </ul>
                            <p className="text-muted-foreground mt-3 text-xs">
                                Съдържанието на изпратените версии е замразено и не се променя при редакция.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>

            <AlertDialog open={confirmSend} onOpenChange={setConfirmSend}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Изпратихте ли вече офертата?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Потвърдете само ако сте я изпратили на клиента. Текущото съдържание ще бъде запазено като версия{' '}
                            {quote.version} и няма да се променя при бъдещи редакции.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Отказ</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                setStatus('sent');
                                setConfirmSend(false);
                            }}
                        >
                            Да, изпратена е
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5">{children}</dd>
        </div>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}
