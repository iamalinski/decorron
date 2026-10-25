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
import { FileText, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { QuoteStatusBadge } from '../../components/quote-status-badge';
import type { CrmOptions, Quote } from '../../types';

interface Filters {
    search: string;
    status: string;
}

interface QuotesIndexProps {
    quotes: Paginated<Quote>;
    filters: Filters;
    options: CrmOptions;
}

export default function QuotesIndex({ quotes, filters, options }: QuotesIndexProps) {
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

    const apply = (patch: Partial<Filters>) => {
        const next = { ...filters, search, ...patch };
        const query: Record<string, string> = {};

        if (next.search) query.search = next.search;
        if (next.status && next.status !== 'all') query.status = next.status;

        router.get(route('crm.quotes.index'), query, { preserveState: true, replace: true });
    };

    const items = quotes.data;

    return (
        <AppLayout
            title="Оферти"
            description={`${quotes.meta.total} ${quotes.meta.total === 1 ? 'оферта' : 'оферти'}`}
            actions={
                <Button asChild size="sm">
                    <Link href={route('crm.quotes.create')}>
                        <Plus aria-hidden="true" /> <span className="hidden sm:inline">Нова оферта</span>
                    </Link>
                </Button>
            }
        >
            <Head title="Оферти" />

            <div className="mx-auto max-w-5xl space-y-4">
                <div className="flex flex-wrap gap-2">
                    <div className="relative min-w-48 flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden="true" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Търсене по номер или клиент"
                            className="h-11 pl-9"
                            aria-label="Търсене в офертите"
                        />
                    </div>

                    <Select value={filters.status} onValueChange={(value) => apply({ status: value })}>
                        <SelectTrigger className="h-11 w-48" aria-label="Статус">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Всички статуси</SelectItem>
                            <SelectItem value="deleted">Кошче</SelectItem>
                            {options.quote_statuses.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {items.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title="Още няма оферти"
                        description="Офертата е документът, който изпращате на клиента. Създава се от калкулация или от запитване."
                        action={
                            <Button asChild>
                                <Link href={route('crm.quotes.create')}>
                                    <Plus aria-hidden="true" /> Нова оферта
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <ul className="grid gap-3 md:hidden">
                            {items.map((quote) => (
                                <li key={quote.id}>
                                    <Link
                                        href={route('crm.quotes.show', quote.id)}
                                        className="bg-card hover:bg-accent/40 focus-visible:ring-ring block rounded-lg border p-4 shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{quote.customer_name}</p>
                                                <p className="text-muted-foreground text-sm">
                                                    {quote.quote_number} · {formatDate(quote.issued_at)}
                                                </p>
                                            </div>
                                            <p className="shrink-0 text-lg font-bold tabular-nums">
                                                {formatMoney(quote.total, quote.currency)}
                                            </p>
                                        </div>
                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                            <QuoteStatusBadge status={quote.status} label={quote.status_label} />
                                            {quote.version > 1 && (
                                                <span className="text-muted-foreground text-xs">версия {quote.version}</span>
                                            )}
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
                                        <TableHead>Издадена</TableHead>
                                        <TableHead>Валидна до</TableHead>
                                        <TableHead>Статус</TableHead>
                                        <TableHead className="text-right">Сума</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((quote) => (
                                        <TableRow
                                            key={quote.id}
                                            className="cursor-pointer"
                                            onClick={() => router.visit(route('crm.quotes.show', quote.id))}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={route('crm.quotes.show', quote.id)}
                                                    className="font-medium hover:underline"
                                                    onClick={(event) => event.stopPropagation()}
                                                >
                                                    {quote.quote_number}
                                                </Link>
                                                {quote.version > 1 && (
                                                    <p className="text-muted-foreground text-xs">версия {quote.version}</p>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{quote.customer_name}</TableCell>
                                            <TableCell className="text-muted-foreground whitespace-nowrap">
                                                {formatDate(quote.issued_at)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground whitespace-nowrap">
                                                {quote.valid_until ? formatDate(quote.valid_until) : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <QuoteStatusBadge status={quote.status} label={quote.status_label} />
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatMoney(quote.total, quote.currency)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>

                        {quotes.meta.last_page > 1 && (
                            <nav className="flex flex-wrap items-center justify-center gap-1" aria-label="Страници">
                                {quotes.links.map((link, index) => (
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
