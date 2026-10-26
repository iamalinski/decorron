import { EmptyState } from '@/components/empty-state';
import { TrashActions } from '@/components/trash-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney, formatPercent } from '@/lib/money';
import type { Paginated } from '@/types';
import type { Calculation } from '@/types/decorron';
import { Head, Link, router } from '@inertiajs/react';
import { History, Plus, Search, SlidersHorizontal, Trash2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Filters {
    search: string;
    from: string | null;
    to: string | null;
    sort: string;
    direction: 'asc' | 'desc';
    status: 'active' | 'deleted';
}

interface IndexPageProps {
    calculations: Paginated<Calculation>;
    trashed_count: number;
    filters: Filters;
}

const SORT_LABELS: Record<string, string> = {
    calculated_at: 'Дата',
    final_price: 'Крайна цена',
    net_profit: 'Чиста печалба',
    service_name: 'Име на услугата',
};

export default function CalculationsIndex({ calculations, trashed_count, filters }: IndexPageProps) {
    const inTrash = filters.status === 'deleted';
    const [search, setSearch] = useState(filters.search ?? '');
    const [showFilters, setShowFilters] = useState(Boolean(filters.from || filters.to));
    const isFirstRender = useRef(true);

    // Debounce на търсенето, за да не се праща заявка при всеки символ.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                route('calculations.index'),
                { ...cleanFilters(filters), search: search || undefined },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const apply = (patch: Partial<Filters>) => {
        router.get(route('calculations.index'), { ...cleanFilters({ ...filters, ...patch }), search: search || undefined }, { preserveState: true });
    };

    const reset = () => {
        setSearch('');
        router.get(route('calculations.index'), {}, { preserveState: false });
    };

    const items = calculations.data;
    const hasFilters = Boolean(search || filters.from || filters.to);

    return (
        <AppLayout
            title="История"
            description={`${calculations.meta.total} ${calculations.meta.total === 1 ? 'калкулация' : 'калкулации'}`}
            actions={
                <Button asChild size="sm">
                    <Link href={route('calculations.create')}>
                        <Plus aria-hidden="true" /> <span className="hidden sm:inline">Нова</span>
                    </Link>
                </Button>
            }
        >
            <Head title="История" />

            <div className="mx-auto max-w-6xl space-y-4">
                <div className="flex gap-2">
                    <div className="relative flex-1">
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Търсене по услуга, клиент или номер"
                            className="h-11 pl-9"
                            aria-label="Търсене в историята"
                        />
                    </div>
                    <Select value={filters.status} onValueChange={(value) => apply({ status: value as Filters['status'] })}>
                        <SelectTrigger className="h-11 w-36 shrink-0" aria-label="Статус">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">История</SelectItem>
                            <SelectItem value="deleted">Кошче{trashed_count > 0 ? ` (${trashed_count})` : ''}</SelectItem>
                        </SelectContent>
                    </Select>

                    <Button
                        type="button"
                        variant={showFilters ? 'default' : 'outline'}
                        size="icon"
                        className="h-11 w-11 shrink-0"
                        onClick={() => setShowFilters((value) => !value)}
                        aria-label="Филтри"
                        aria-expanded={showFilters}
                    >
                        <SlidersHorizontal />
                    </Button>
                </div>

                {showFilters && (
                    <Card>
                        <CardContent className="grid gap-3 pt-6 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="from">От дата</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={filters.from ?? ''}
                                    onChange={(e) => apply({ from: e.target.value || null })}
                                    className="h-11"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="to">До дата</Label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={filters.to ?? ''}
                                    onChange={(e) => apply({ to: e.target.value || null })}
                                    className="h-11"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="sort">Подреждане</Label>
                                <Select value={filters.sort} onValueChange={(value) => apply({ sort: value })}>
                                    <SelectTrigger id="sort" className="h-11">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(SORT_LABELS).map(([value, label]) => (
                                            <SelectItem key={value} value={value}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="direction">Посока</Label>
                                <Select value={filters.direction} onValueChange={(value) => apply({ direction: value as 'asc' | 'desc' })}>
                                    <SelectTrigger id="direction" className="h-11">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="desc">Низходящо</SelectItem>
                                        <SelectItem value="asc">Възходящо</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {hasFilters && (
                                <Button type="button" variant="ghost" onClick={reset} className="justify-self-start">
                                    <X aria-hidden="true" /> Изчисти филтрите
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}

                {items.length === 0 ? (
                    <EmptyState
                        icon={inTrash ? Trash2 : History}
                        title={inTrash ? 'Кошчето е празно' : hasFilters ? 'Няма резултати' : 'Още няма записани калкулации'}
                        description={
                            inTrash
                                ? 'Изтритите калкулации остават тук, докато не ги върнете или изтриете окончателно.'
                                : hasFilters
                                  ? 'Опитайте с друго търсене или изчистете филтрите.'
                                  : 'Направете първата си калкулация — отнема по-малко от минута.'
                        }
                        action={
                            inTrash ? undefined : hasFilters ? (
                                <Button variant="outline" onClick={reset}>
                                    Изчисти филтрите
                                </Button>
                            ) : (
                                <Button asChild>
                                    <Link href={route('calculations.create')}>
                                        <Plus aria-hidden="true" /> Нова калкулация
                                    </Link>
                                </Button>
                            )
                        }
                    />
                ) : (
                    <>
                        {/* Карти на телефон */}
                        <ul className="grid gap-3 md:hidden">
                            {items.map((calculation) => (
                                <li key={calculation.id}>
                                    <Link
                                        href={route('calculations.show', calculation.id)}
                                        className="bg-card hover:bg-accent/40 focus-visible:ring-ring block rounded-lg border p-4 shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{calculation.service_name}</p>
                                                <p className="text-muted-foreground truncate text-sm">
                                                    {calculation.client_name || 'Без клиент'} · {formatDate(calculation.calculated_at)}
                                                </p>
                                            </div>
                                            <p className="shrink-0 text-lg font-bold tabular-nums">
                                                {formatMoney(calculation.final_price, calculation.currency)}
                                            </p>
                                        </div>
                                        <div className="text-muted-foreground mt-3 flex flex-wrap items-center gap-2 text-xs">
                                            <Badge variant="secondary">{calculation.calculation_number}</Badge>
                                            <span>Разходи {formatMoney(calculation.total_cost, calculation.currency)}</span>
                                            <span>·</span>
                                            <span>Печалба {formatMoney(calculation.net_profit, calculation.currency)}</span>
                                            <span>·</span>
                                            <span>Марж {formatPercent(calculation.actual_net_margin)}</span>
                                        </div>
                                    </Link>
                                    {inTrash && (
                                        <div className="mt-2 flex justify-end gap-2">
                                            <CalculationTrashActions calculation={calculation} />
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>

                        {/* Таблица на широк екран */}
                        <Card className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Услуга</TableHead>
                                        <TableHead>Клиент</TableHead>
                                        <TableHead>Дата</TableHead>
                                        <TableHead className="text-right">Материали</TableHead>
                                        <TableHead className="text-right">Труд</TableHead>
                                        <TableHead className="text-right">Други</TableHead>
                                        <TableHead className="text-right">Крайна цена</TableHead>
                                        <TableHead className="text-right">Чиста печалба</TableHead>
                                        <TableHead className="text-right">Марж</TableHead>
                                        {inTrash && <TableHead className="text-right">Действия</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((calculation) => (
                                        <TableRow
                                            key={calculation.id}
                                            className="cursor-pointer"
                                            onClick={() => router.visit(route('calculations.show', calculation.id))}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={route('calculations.show', calculation.id)}
                                                    className="font-medium hover:underline"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    {calculation.service_name}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">{calculation.calculation_number}</p>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{calculation.client_name || '—'}</TableCell>
                                            <TableCell className="text-muted-foreground whitespace-nowrap">
                                                {formatDate(calculation.calculated_at)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(calculation.material_cost_total, calculation.currency)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(calculation.labor_cost, calculation.currency)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(calculation.other_costs, calculation.currency)}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums">
                                                {formatMoney(calculation.final_price, calculation.currency)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(calculation.net_profit, calculation.currency)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{formatPercent(calculation.actual_net_margin)}</TableCell>
                                            {inTrash && (
                                                <TableCell className="text-right" onClick={(event) => event.stopPropagation()}>
                                                    <div className="flex justify-end gap-1">
                                                        <CalculationTrashActions calculation={calculation} compact />
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>

                        {calculations.meta.last_page > 1 && (
                            <nav className="flex flex-wrap items-center justify-center gap-1" aria-label="Страници">
                                {calculations.links.map((link, index) => (
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

/** Бутоните „Възстанови“ и „Изтрий окончателно“ за един ред от Кошчето. */
function CalculationTrashActions({ calculation, compact }: { calculation: Calculation; compact?: boolean }) {
    return (
        <TrashActions
            compact={compact}
            deletedAt={calculation.deleted_at}
            name={`Калкулация ${calculation.calculation_number}`}
            subject="калкулацията"
            destroyUrl={route('calculations.destroy', calculation.id)}
            restoreUrl={route('calculations.restore', calculation.id)}
            forceUrl={route('calculations.force-destroy', calculation.id)}
            alsoRemoved="Редовете с материали изчезват заедно с нея."
            restoreDescription={`Калкулация ${calculation.calculation_number} ще се появи отново в историята и в справките.`}
        />
    );
}

function cleanFilters(filters: Partial<Filters>): Record<string, string> {
    const output: Record<string, string> = {};

    if (filters.from) output.from = filters.from;
    if (filters.to) output.to = filters.to;
    if (filters.sort && filters.sort !== 'calculated_at') output.sort = filters.sort;
    if (filters.direction && filters.direction !== 'desc') output.direction = filters.direction;
    if (filters.status && filters.status !== 'active') output.status = filters.status;

    return output;
}
