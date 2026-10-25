import { TrashActions, TrashBanner } from '@/components/trash-actions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney } from '@/lib/money';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Phone } from 'lucide-react';
import { OrderStatusBadge } from '../../components/order-status-badge';
import type { CrmOptions, Order, Quote } from '../../types';

interface OrderShowProps {
    order: { data: Order };
    quote: { data: Quote } | null;
    options: CrmOptions;
}

export default function OrderShow({ order: wrapper, quote, options }: OrderShowProps) {
    const order = wrapper.data;
    const isTrashed = Boolean(order.deleted_at);

    const changeStatus = (status: string) => {
        router.patch(route('crm.orders.status', order.id), { status }, { preserveScroll: true });
    };

    return (
        <AppLayout
            title={order.order_number}
            description={order.customer_name}
            actions={
                <>
                    {!isTrashed && (
                        <Button asChild size="sm" variant="outline">
                            <Link href={route('crm.orders.edit', order.id)}>
                                <Pencil aria-hidden="true" /> <span className="hidden sm:inline">Редакция</span>
                            </Link>
                        </Button>
                    )}
                    <TrashActions
                        deletedAt={order.deleted_at}
                        blockers={order.deletion_blockers}
                        name={`Поръчка ${order.order_number}`}
                        subject="поръчката"
                        destroyUrl={route('crm.orders.destroy', order.id)}
                        restoreUrl={route('crm.orders.restore', order.id)}
                        forceUrl={route('crm.orders.force-destroy', order.id)}
                        alsoRemoved="Договорените редове на поръчката я следват."
                    />
                </>
            }
        >
            <Head title={order.order_number} />

            <div className="mx-auto max-w-3xl space-y-4">
                <Button asChild variant="ghost" size="sm" className="-ml-2">
                    <Link href={route('crm.orders.index')}>
                        <ArrowLeft aria-hidden="true" /> Към поръчките
                    </Link>
                </Button>

                {isTrashed && (
                    <TrashBanner>
                        Тази поръчка е в Кошчето. Тя не се показва в календара и в справките за приходи, докато не
                        бъде върната.
                    </TrashBanner>
                )}

                <Card>
                    <CardHeader className="gap-2">
                        <OrderStatusBadge status={order.status} label={order.status_label} className="w-fit" />
                        <CardTitle className="text-base">Статус на изпълнението</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid max-w-sm gap-1.5">
                            <Label htmlFor="order-status">Промени статуса</Label>
                            <Select value={order.status} onValueChange={changeStatus}>
                                <SelectTrigger id="order-status" className="h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.order_statuses.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Данни</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                        <Field label="Клиент">
                            <Link href={route('crm.customers.show', order.customer_id)} className="hover:underline">
                                {order.customer_name}
                            </Link>
                        </Field>
                        <Field label="Телефон">
                            {order.customer_phone ? (
                                <a href={`tel:${order.customer_phone}`} className="inline-flex items-center gap-1 hover:underline">
                                    <Phone className="size-3.5" aria-hidden="true" /> {order.customer_phone}
                                </a>
                            ) : (
                                '—'
                            )}
                        </Field>
                        <Field label="Дата на събитието">{order.event_date ? formatDate(order.event_date) : '—'}</Field>
                        <Field label="Място">{order.event_location ?? '—'}</Field>
                        {order.crm_opportunity_id && (
                            <Field label="Запитване">
                                <Link href={route('crm.opportunities.show', order.crm_opportunity_id)} className="hover:underline">
                                    {order.opportunity_title ?? 'Виж запитването'}
                                </Link>
                            </Field>
                        )}
                        {quote && (
                            <Field label="Приета оферта">
                                <Link href={route('crm.quotes.show', quote.data.id)} className="hover:underline">
                                    {quote.data.quote_number} (версия {order.quote_version ?? quote.data.version})
                                </Link>
                            </Field>
                        )}
                        {order.deadline_note && <Field label="Срокове">{order.deadline_note}</Field>}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Договорени услуги</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
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
                                {(order.items ?? []).map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            {item.description}
                                            {item.details && <p className="text-muted-foreground text-xs">{item.details}</p>}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{Number(item.quantity)}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(item.unit_price, order.currency)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(item.line_total, order.currency)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="flex items-baseline justify-between border-t pt-3">
                            <span className="font-medium">Договорена сума</span>
                            <span className="text-xl font-bold tabular-nums">{formatMoney(order.agreed_total, order.currency)}</span>
                        </div>

                        {order.deposit_amount && (
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">Капаро</span>
                                <span className="tabular-nums">{formatMoney(order.deposit_amount, order.currency)}</span>
                            </div>
                        )}

                        <p className="text-muted-foreground text-xs">
                            Сумите са договорените по поръчката. Отчитането на реални плащания не е част от тази версия.
                        </p>
                    </CardContent>
                </Card>

                {order.execution_notes && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Бележки за изпълнението</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm whitespace-pre-wrap">{order.execution_notes}</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
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
