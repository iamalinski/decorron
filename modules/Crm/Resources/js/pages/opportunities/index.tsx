import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney } from '@/lib/money';
import type { Collection, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Columns3, List, Plus, Search, Target, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { OpportunityFormDialog } from '../../components/opportunity-form-dialog';
import { StageBadge, StatusBadge } from '../../components/stage-badge';
import type { CrmOptions, Customer, Opportunity } from '../../types';

interface Filters {
    search: string;
    stage: string;
    status: string;
    customer_id: number | null;
    view: 'list' | 'board';
    from: string | null;
    to: string | null;
}

interface OpportunitiesIndexProps {
    opportunities: Paginated<Opportunity> | Collection<Opportunity>;
    filters: Filters;
    options: CrmOptions;
    customers: Collection<Customer>;
}

const ALL = '__all__';

export default function OpportunitiesIndex({ opportunities, filters, options, customers }: OpportunitiesIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
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

    const apply = (patch: Partial<Filters> & { search?: string }) => {
        router.get(route('crm.opportunities.index'), cleanFilters({ ...filters, search, ...patch }), {
            preserveState: true,
            replace: true,
        });
    };

    const reset = () => {
        setSearch('');
        router.get(route('crm.opportunities.index'), filters.view === 'board' ? { view: 'board' } : {}, { preserveState: false });
    };

    const items = opportunities.data;
    const paginated = 'meta' in opportunities ? opportunities : null;
    const hasFilters = Boolean(search || filters.stage || filters.status !== 'open' || filters.from || filters.to);

    return (
        <AppLayout
            title="Запитвания"
            description={paginated ? `${paginated.meta.total} записа` : `${items.length} отворени`}
            actions={
                <Button size="sm" onClick={() => setDialogOpen(true)}>
                    <Plus aria-hidden="true" /> <span className="hidden sm:inline">Ново запитване</span>
                </Button>
            }
        >
            <Head title="Запитвания" />

            <div className="mx-auto max-w-6xl space-y-4">
                <div className="flex flex-wrap gap-2">
                    <div className="relative min-w-48 flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden="true" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Търсене по заглавие, клиент или място"
                            className="h-11 pl-9"
                            aria-label="Търсене в запитванията"
                        />
                    </div>

                    <Select
                        value={filters.stage === '' ? ALL : filters.stage}
                        onValueChange={(value) => apply({ stage: value === ALL ? '' : value })}
                    >
                        <SelectTrigger className="h-11 w-44" aria-label="Етап">
                            <SelectValue placeholder="Всички етапи" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Всички етапи</SelectItem>
                            {options.stages.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={filters.status} onValueChange={(value) => apply({ status: value })}>
                        <SelectTrigger className="h-11 w-36" aria-label="Статус">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {options.statuses.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                            <SelectItem value="all">Всички</SelectItem>
                            <SelectItem value="deleted">Кошче</SelectItem>
                        </SelectContent>
                    </Select>

                    <div className="flex gap-1">
                        <Button
                            type="button"
                            size="icon"
                            variant={filters.view === 'list' ? 'default' : 'outline'}
                            className="h-11 w-11"
                            aria-label="Списък"
                            aria-pressed={filters.view === 'list'}
                            onClick={() => apply({ view: 'list' })}
                        >
                            <List />
                        </Button>
                        <Button
                            type="button"
                            size="icon"
                            variant={filters.view === 'board' ? 'default' : 'outline'}
                            className="h-11 w-11"
                            aria-label="Дъска по етапи"
                            aria-pressed={filters.view === 'board'}
                            onClick={() => apply({ view: 'board' })}
                        >
                            <Columns3 />
                        </Button>
                    </div>

                    {hasFilters && (
                        <Button type="button" variant="ghost" className="h-11" onClick={reset}>
                            <X aria-hidden="true" /> Изчисти
                        </Button>
                    )}
                </div>

                {items.length === 0 ? (
                    <EmptyState
                        icon={Target}
                        title={hasFilters ? 'Няма намерени запитвания' : 'Още няма запитвания'}
                        description={
                            hasFilters
                                ? 'Опитайте с друго търсене или изчистете филтрите.'
                                : 'Запитването описва едно конкретно събитие за даден клиент и следи пътя му до спечелена поръчка.'
                        }
                        action={
                            hasFilters ? (
                                <Button variant="outline" onClick={reset}>
                                    Изчисти филтрите
                                </Button>
                            ) : (
                                <Button onClick={() => setDialogOpen(true)}>
                                    <Plus aria-hidden="true" /> Ново запитване
                                </Button>
                            )
                        }
                    />
                ) : filters.view === 'board' ? (
                    <Board items={items} options={options} />
                ) : (
                    <>
                        <ul className="grid gap-3 md:hidden">
                            {items.map((opportunity) => (
                                <li key={opportunity.id}>
                                    <OpportunityCard opportunity={opportunity} />
                                </li>
                            ))}
                        </ul>

                        <Card className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Запитване</TableHead>
                                        <TableHead>Клиент</TableHead>
                                        <TableHead>Събитие</TableHead>
                                        <TableHead>Етап</TableHead>
                                        <TableHead>Следващо действие</TableHead>
                                        <TableHead className="text-right">Бюджет</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((opportunity) => (
                                        <TableRow
                                            key={opportunity.id}
                                            className="cursor-pointer"
                                            onClick={() => router.visit(route('crm.opportunities.show', opportunity.id))}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={route('crm.opportunities.show', opportunity.id)}
                                                    className="font-medium hover:underline"
                                                    onClick={(event) => event.stopPropagation()}
                                                >
                                                    {opportunity.title}
                                                </Link>
                                                <StatusBadge
                                                    status={opportunity.status}
                                                    label={opportunity.status_label}
                                                    className="ml-2"
                                                />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{opportunity.customer_name ?? '—'}</TableCell>
                                            <TableCell className="text-muted-foreground whitespace-nowrap">
                                                {opportunity.event_date ? formatDate(opportunity.event_date) : '—'}
                                                {opportunity.event_type && (
                                                    <span className="block text-xs">{opportunity.event_type}</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <StageBadge stage={opportunity.stage} label={opportunity.stage_label} />
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {opportunity.next_action ?? '—'}
                                                {opportunity.next_action_due && (
                                                    <span className="block text-xs">до {formatDate(opportunity.next_action_due)}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {opportunity.expected_budget ? formatMoney(opportunity.expected_budget) : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>

                        {paginated && paginated.meta.last_page > 1 && (
                            <nav className="flex flex-wrap items-center justify-center gap-1" aria-label="Страници">
                                {paginated.links.map((link, index) => (
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

            <OpportunityFormDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                options={options}
                customers={customers.data}
            />
        </AppLayout>
    );
}

function Board({ items, options }: { items: Opportunity[]; options: CrmOptions }) {
    return (
        <div className="-mx-4 overflow-x-auto px-4 pb-2 md:-mx-6 md:px-6">
            <div className="flex gap-3">
                {options.stages.map((stage) => {
                    const columnItems = items.filter((opportunity) => opportunity.stage === stage.value);

                    return (
                        <section key={stage.value} className="w-64 shrink-0" aria-label={stage.label}>
                            <div className="mb-2 flex items-center justify-between px-1">
                                <h2 className="text-sm font-medium">{stage.label}</h2>
                                <span className="text-muted-foreground text-xs tabular-nums">{columnItems.length}</span>
                            </div>
                            <ul className="grid gap-2">
                                {columnItems.map((opportunity) => (
                                    <li key={opportunity.id}>
                                        <OpportunityCard opportunity={opportunity} compact />
                                    </li>
                                ))}
                                {columnItems.length === 0 && (
                                    <li className="text-muted-foreground rounded-lg border border-dashed px-3 py-6 text-center text-xs">
                                        Няма запитвания
                                    </li>
                                )}
                            </ul>
                        </section>
                    );
                })}
            </div>
        </div>
    );
}

function OpportunityCard({ opportunity, compact = false }: { opportunity: Opportunity; compact?: boolean }) {
    return (
        <Link
            href={route('crm.opportunities.show', opportunity.id)}
            className="bg-card hover:bg-accent/40 focus-visible:ring-ring block rounded-lg border p-3 shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
        >
            <p className="truncate font-medium">{opportunity.title}</p>
            <p className="text-muted-foreground mt-0.5 truncate text-sm">{opportunity.customer_name ?? '—'}</p>

            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                {!compact && <StageBadge stage={opportunity.stage} label={opportunity.stage_label} />}
                <StatusBadge status={opportunity.status} label={opportunity.status_label} />
                {opportunity.event_date && (
                    <span className="text-muted-foreground text-xs">{formatDate(opportunity.event_date)}</span>
                )}
                {opportunity.expected_budget && (
                    <span className="text-muted-foreground text-xs tabular-nums">· {formatMoney(opportunity.expected_budget)}</span>
                )}
            </div>
        </Link>
    );
}

function cleanFilters(filters: Partial<Filters> & { search?: string }): Record<string, string> {
    const output: Record<string, string> = {};

    if (filters.search) output.search = filters.search;
    if (filters.stage) output.stage = filters.stage;
    if (filters.status && filters.status !== 'open') output.status = filters.status;
    if (filters.customer_id) output.customer_id = String(filters.customer_id);
    if (filters.view === 'board') output.view = 'board';
    if (filters.from) output.from = filters.from;
    if (filters.to) output.to = filters.to;

    return output;
}
