import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatUnitPrice } from '@/lib/money';
import type { Collection, Paginated, SharedData } from '@/types';
import type { Material, Unit } from '@/types/decorron';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, ArchiveRestore, Calculator, Package, Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { MaterialFormDialog } from '../../components/material-form-dialog';

interface Filters {
    search: string;
    category: string;
    status: 'active' | 'archived' | 'all';
}

interface MaterialsPageProps {
    materials: Paginated<Material>;
    units: Collection<Unit>;
    categories: string[];
    filters: Filters;
}

export default function MaterialsIndex({ materials, units, categories, filters }: MaterialsPageProps) {
    const currency = usePage<SharedData>().props.app.currency;

    const [search, setSearch] = useState(filters.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Material | null>(null);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                route('materials.index'),
                { search: search || undefined, category: filters.category || undefined, status: filters.status },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const apply = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };

        router.get(
            route('materials.index'),
            { search: search || undefined, category: next.category || undefined, status: next.status },
            { preserveState: true },
        );
    };

    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };

    const openEdit = (material: Material) => {
        setEditing(material);
        setDialogOpen(true);
    };

    const toggleArchive = (material: Material) => {
        router.patch(route('materials.archive', material.id), {}, { preserveScroll: true });
    };

    const items = materials.data;

    return (
        <AppLayout
            title="Материали"
            description={`${materials.meta.total} ${materials.meta.total === 1 ? 'материал' : 'материала'} в каталога`}
            actions={
                <Button size="sm" onClick={openCreate}>
                    <Plus aria-hidden="true" /> <span className="hidden sm:inline">Нов материал</span>
                </Button>
            }
        >
            <Head title="Материали" />

            <div className="mx-auto max-w-6xl space-y-4">
                <div className="flex flex-col gap-2 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Търсене по име, код или категория"
                            className="h-11 pl-9"
                            aria-label="Търсене в каталога"
                        />
                    </div>

                    <div className="flex gap-2">
                        <Select value={filters.category || 'all'} onValueChange={(value) => apply({ category: value === 'all' ? '' : value })}>
                            <SelectTrigger className="h-11 flex-1 sm:w-44" aria-label="Категория">
                                <SelectValue placeholder="Категория" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Всички категории</SelectItem>
                                {categories.map((category) => (
                                    <SelectItem key={category} value={category}>
                                        {category}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select value={filters.status} onValueChange={(value) => apply({ status: value as Filters['status'] })}>
                            <SelectTrigger className="h-11 flex-1 sm:w-36" aria-label="Статус">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Активни</SelectItem>
                                <SelectItem value="archived">Архивирани</SelectItem>
                                <SelectItem value="all">Всички</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {items.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title={search || filters.category ? 'Няма намерени материали' : 'Каталогът е празен'}
                        description="Добавете материалите, които използвате най-често, за да въвеждате калкулациите по-бързо."
                        action={
                            <Button onClick={openCreate}>
                                <Plus aria-hidden="true" /> Нов материал
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <ul className="grid gap-3 md:hidden">
                            {items.map((material) => (
                                <li key={material.id} className="rounded-lg border bg-card p-4 shadow-xs">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{material.name}</p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {material.category || 'Без категория'}
                                                {material.sku ? ` · ${material.sku}` : ''}
                                            </p>
                                        </div>
                                        <p className="shrink-0 font-semibold tabular-nums">
                                            {formatUnitPrice(material.default_unit_price, currency)}
                                            <span className="text-xs font-normal text-muted-foreground">/{material.unit_symbol}</span>
                                        </p>
                                    </div>
                                    <div className="mt-3 flex items-center gap-2">
                                        {!material.is_active && <Badge variant="secondary">Архивиран</Badge>}
                                        <Button size="sm" variant="outline" onClick={() => openEdit(material)} className="ml-auto">
                                            <Pencil aria-hidden="true" /> Редакция
                                        </Button>
                                        <Button size="sm" variant="ghost" onClick={() => toggleArchive(material)}>
                                            {material.is_active ? <Archive aria-hidden="true" /> : <ArchiveRestore aria-hidden="true" />}
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>

                        <Card className="hidden md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Материал</TableHead>
                                        <TableHead>Код</TableHead>
                                        <TableHead>Категория</TableHead>
                                        <TableHead>Мярка</TableHead>
                                        <TableHead className="text-right">Стандартна цена</TableHead>
                                        <TableHead>Статус</TableHead>
                                        <TableHead className="text-right">Действия</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((material) => (
                                        <TableRow key={material.id}>
                                            <TableCell className="font-medium">{material.name}</TableCell>
                                            <TableCell className="text-muted-foreground">{material.sku || '—'}</TableCell>
                                            <TableCell className="text-muted-foreground">{material.category || '—'}</TableCell>
                                            <TableCell className="text-muted-foreground">{material.unit_symbol}</TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatUnitPrice(material.default_unit_price, currency)}
                                            </TableCell>
                                            <TableCell>
                                                {material.is_active ? (
                                                    <Badge variant="outline">Активен</Badge>
                                                ) : (
                                                    <Badge variant="secondary">Архивиран</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button size="sm" variant="ghost" onClick={() => openEdit(material)} aria-label="Редакция">
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => toggleArchive(material)}
                                                    aria-label={material.is_active ? 'Архивирай' : 'Активирай'}
                                                >
                                                    {material.is_active ? <Archive /> : <ArchiveRestore />}
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>

                        {materials.meta.last_page > 1 && (
                            <nav className="flex flex-wrap items-center justify-center gap-1" aria-label="Страници">
                                {materials.links.map((link, index) => (
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

                <p className="text-xs text-muted-foreground">
                    Материалите не се изтриват, а се архивират — така записаните калкулации остават непроменени.{' '}
                    <Link href={route('calculations.create')} className="inline-flex items-center gap-1 underline">
                        <Calculator className="size-3" aria-hidden="true" /> Нова калкулация
                    </Link>
                </p>
            </div>

            <MaterialFormDialog open={dialogOpen} onOpenChange={setDialogOpen} units={units.data} material={editing} />
        </AppLayout>
    );
}
