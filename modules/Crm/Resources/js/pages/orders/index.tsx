import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney } from '@/lib/money';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { OrderStatusBadge } from '../../components/order-status-badge';
import type { CrmOptions, Order } from '../../types';

interface OrdersIndexProps {
    orders: Paginated<Order>;
    filters: { search: string; status: string };
    options: CrmOptions;
}

export default function OrdersIndex({ orders, filters, options }: OrdersIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = window.setTimeout(() => apply({ search }), 350);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const apply = (patch: Partial<typeof filters>) => {
        const next = { ...filters, search, ...patch };
        const query: Record<string, string> = {};

        if (next.search) query.search = next.search;
        if (next.status && next.status !== 'active') query.status = next.status;

        router.get(route('crm.orders.index'), query, { preserveState: true, replace: true });
    };

    const items = orders.data;

    return (
        <AppLayout
            title="Поръчки"
            description={`${orders.meta.total} ${orders.meta.total === 1 ? 'поръчка' : 'поръчки'}`}
            actions={
                <Button asChild size="sm">
                    <Link href={route('crm.orders.create')}>
                        <Plus aria-hidden="true" /> <span className="hidden sm:inline">Нова поръчка</span>
                    </Link>
                </Button>
            }
        >
            <Head title="Поръчки" />

            <div className="mx-auto max-w-5xl space-y-4">
                <div className="flex flex-wrap gap-2">
                    <div className="relative min-w-48 flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden="true" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Търсене по номер или клиент"
                            className="h-11 pl-9"
                            aria-label="Търсене в поръчките"
                        />
                    </div>

                    <Select value={filters.status} onValueChange={(value) => apply({ status: value })}>
                        <SelectTrigger className="h-11 w-52" aria-label="Статус">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Предстоящи</SelectItem>
                            <SelectItem value="all">Всички</SelectItem>
                            <SelectItem value="deleted">Кошче</SelectItem>
                            {options.order_statuses.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {items.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Още няма поръчки"
                        description="Поръчката е потвърдена работа. Създава се от приета оферта или директно, когато клиентът е потвърдил."
                        action={
                            <Button asChild>
                                <Link href={route('crm.orders.create')}>
                                    <Plus aria-hidden="true" /> Нова поръчка
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <ul className="grid gap-3 md:hidden">
                            {items.map((order) => (
                                <li key={order.id}>
                                    <Link
                                        href={route('crm.orders.show', order.id)}
                                        className="bg-card hover:bg-accent/40 focus-visible:ring-ring block rounded-lg border p-4 shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{order.customer_name}</p>
                                                <p className="text-muted-foreground text-sm">
                                                    {order.order_number}
                                                    {order.event_date ? ` · ${formatDate(order.event_date)}` : ''}
                                                </p>
                                            </div>
                                            <p className="shrink-0 text-lg font-bold tabular-nums">
                                                {formatMoney(order.agreed_total, order.currency)}
                                            </p>
                                        </div>
                                        <div className="mt-2">
                                            <OrderStatusBadge status={order.status} label={order.status_label} />
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <Card className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Номер</TableHead>
                                        <TableHead>Клиент</TableHead>
                                        <TableHead>Събитие</TableHead>
                                        <TableHead>Статус</TableHead>
                                        <TableHead className="text-right">Договорена сума</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((order) => (
                                        <TableRow
                                            key={order.id}
                                            className="cursor-pointer"
                                            onClick={() => router.visit(route('crm.orders.show', order.id))}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={route('crm.orders.show', order.id)}
                                                    className="font-medium hover:underline"
                                                    onClick={(event) => event.stopPropagation()}
                                                >
                                                    {order.order_number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{order.customer_name}</TableCell>
                                            <TableCell className="text-muted-foreground whitespace-nowrap">
                                                {order.event_date ? formatDate(order.event_date) : '—'}
                                                {order.event_location && (
                                                    <span className="block text-xs">{order.event_location}</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <OrderStatusBadge status={order.status} label={order.status_label} />
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatMoney(order.agreed_total, order.currency)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>

                        {orders.meta.last_page > 1 && (
                            <nav className="flex flex-wrap items-center justify-center gap-1" aria-label="Страници">
                                {orders.links.map((link, index) => (
                                    <Button
                                        key={index}
                                        type="button"
                                        size="sm"
                                        variant={link.active ? 'default' : 'outline'}
                                        disabled={!link.url}
                                        onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </nav>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
