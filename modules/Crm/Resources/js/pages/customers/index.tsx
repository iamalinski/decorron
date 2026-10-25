import { EmptyState } from '@/components/empty-state';
import { TrashActions } from '@/components/trash-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Users, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CustomerFormDialog } from '../../components/customer-form-dialog';
import type { CrmOptions, Customer } from '../../types';

interface Filters {
    search: string;
    status: 'active' | 'archived' | 'all' | 'deleted';
    source: string;
    sort: 'name' | 'created_at';
    direction: 'asc' | 'desc';
}

interface CustomersIndexProps {
    customers: Paginated<Customer>;
    filters: Filters;
    options: CrmOptions;
}

const ALL_SOURCES = '__all__';

export default function CustomersIndex({ customers, filters, options }: CustomersIndexProps) {
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

    const apply = (patch: Partial<Filters>) => {
        router.get(route('crm.customers.index'), cleanFilters({ ...filters, search, ...patch }), {
            preserveState: true,
            replace: true,
        });
    };

    const reset = () => {
        setSearch('');
        router.get(route('crm.customers.index'), {}, { preserveState: false });
    };

    const items = customers.data;
    const inTrash = filters.status === 'deleted';
    const hasFilters = Boolean(search || filters.source || filters.status !== 'active');

    return (
        <AppLayout
            title="Клиенти"
            description={`${customers.meta.total} ${customers.meta.total === 1 ? 'клиент' : 'клиенти'}`}
            actions={
                <Button size="sm" onClick={() => setDialogOpen(true)}>
                    <Plus aria-hidden="true" /> <span className="hidden sm:inline">Нов клиент</span>
                </Button>
            }
        >
            <Head title="Клиенти" />

            <div className="mx-auto max-w-5xl space-y-4">
                <div className="flex flex-wrap gap-2">
                    <div className="relative min-w-48 flex-1">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden="true" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Търсене по име, телефон или имейл"
                            className="h-11 pl-9"
                            aria-label="Търсене на клиенти"
                        />
                    </div>

                    <Select value={filters.status} onValueChange={(value) => apply({ status: value as Filters['status'] })}>
                        <SelectTrigger className="h-11 w-36" aria-label="Статус">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Активни</SelectItem>
                            <SelectItem value="archived">Архивирани</SelectItem>
                            <SelectItem value="all">Всички</SelectItem>
                            <SelectItem value="deleted">Кошче</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.source === '' ? ALL_SOURCES : filters.source}
                        onValueChange={(value) => apply({ source: value === ALL_SOURCES ? '' : value })}
                    >
                        <SelectTrigger className="h-11 w-40" aria-label="Източник">
                            <SelectValue placeholder="Всички източници" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_SOURCES}>Всички източници</SelectItem>
                            {options.sources.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button type="button" variant="ghost" className="h-11" onClick={reset}>
                            <X aria-hidden="true" /> Изчисти
                        </Button>
                    )}
                </div>

                {items.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={hasFilters ? 'Няма намерени клиенти' : 'Още няма клиенти'}
                        description={
                            hasFilters
                                ? 'Опитайте с друго търсене или изчистете филтрите.'
                                : 'Добавете първия си клиент — после ще можете да свързвате калкулации, оферти и поръчки с него.'
                        }
                        action={
                            hasFilters ? (
                                <Button variant="outline" onClick={reset}>
                                    Изчисти филтрите
                                </Button>
                            ) : (
                                <Button onClick={() => setDialogOpen(true)}>
                                    <Plus aria-hidden="true" /> Нов клиент
                                </Button>
                            )
                        }
                    />
                ) : (
                    <>
                        {/* Карти на телефон */}
                        <ul className="grid gap-3 md:hidden">
                            {items.map((customer) => (
                                <li key={customer.id}>
                                    <Link
                                        href={route('crm.customers.show', customer.id)}
                                        className="bg-card hover:bg-accent/40 focus-visible:ring-ring block rounded-lg border p-4 shadow-xs transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="min-w-0 truncate font-medium">{customer.name}</p>
                                            {customer.is_archived && <Badge variant="outline">Архивиран</Badge>}
                                        </div>
                                        <p className="text-muted-foreground mt-1 truncate text-sm">
                                            {[customer.phone, customer.email].filter(Boolean).join(' · ') || 'Без контакти'}
                                        </p>
                                        <div className="text-muted-foreground mt-2 flex flex-wrap gap-2 text-xs">
                                            <Badge variant="secondary">{customer.type_label}</Badge>
                                            {customer.source_label && <span>Източник: {customer.source_label}</span>}
                                            {customer.city && <span>· {customer.city}</span>}
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        {/* Таблица на широк екран */}
                        <Card className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Име</TableHead>
                                        <TableHead>Тип</TableHead>
                                        <TableHead>Телефон</TableHead>
                                        <TableHead>Имейл</TableHead>
                                        <TableHead>Град</TableHead>
                                        <TableHead>Източник</TableHead>
                                        {inTrash && <TableHead className="text-right">Действия</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((customer) => (
                                        <TableRow
                                            key={customer.id}
                                            className="cursor-pointer"
                                            onClick={() => router.visit(route('crm.customers.show', customer.id))}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={route('crm.customers.show', customer.id)}
                                                    className="font-medium hover:underline"
                                                    onClick={(event) => event.stopPropagation()}
                                                >
                                                    {customer.name}
                                                </Link>
                                                {customer.is_archived && (
                                                    <Badge variant="outline" className="ml-2">
                                                        Архивиран
                                                    </Badge>
                                                )}
                                                {customer.contact_person && (
                                                    <p className="text-muted-foreground text-xs">{customer.contact_person}</p>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">{customer.type_label}</TableCell>
                                            <TableCell className="text-muted-foreground whitespace-nowrap">{customer.phone || '—'}</TableCell>
                                            <TableCell className="text-muted-foreground">{customer.email || '—'}</TableCell>
                                            <TableCell className="text-muted-foreground">{customer.city || '—'}</TableCell>
                                            <TableCell className="text-muted-foreground">{customer.source_label || '—'}</TableCell>
                                            {inTrash && (
                                                <TableCell className="text-right" onClick={(event) => event.stopPropagation()}>
                                                    <div className="flex justify-end gap-1">
                                                        <TrashActions
                                                            compact
                                                            deletedAt={customer.deleted_at}
                                                            name={`Клиентът „${customer.name}“`}
                                                            subject="клиента"
                                                            destroyUrl={route('crm.customers.destroy', customer.id)}
                                                            restoreUrl={route('crm.customers.restore', customer.id)}
                                                            forceUrl={route('crm.customers.force-destroy', customer.id)}
                                                        />
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>

                        {customers.meta.last_page > 1 && (
                            <nav className="flex flex-wrap items-center justify-center gap-1" aria-label="Страници">
                                {customers.links.map((link, index) => (
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

            <CustomerFormDialog open={dialogOpen} onOpenChange={setDialogOpen} options={options} initialName={search} />
        </AppLayout>
    );
}

function cleanFilters(filters: Partial<Filters> & { search?: string }): Record<string, string> {
    const output: Record<string, string> = {};

    if (filters.search) output.search = filters.search;
    if (filters.status && filters.status !== 'active') output.status = filters.status;
    if (filters.source) output.source = filters.source;
    if (filters.sort && filters.sort !== 'name') output.sort = filters.sort;
    if (filters.direction && filters.direction !== 'asc') output.direction = filters.direction;

    return output;
}
