import { EmptyState } from '@/components/empty-state';
import { TrashActions } from '@/components/trash-actions';
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
import { Archive, ArchiveRestore, Calculator, Package, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { MaterialFormDialog } from '../../components/material-form-dialog';

interface Filters {
    search: string;
    category: string;
    status: 'active' | 'archived' | 'all' | 'deleted';
}

interface MaterialsPageProps {
    materials: Paginated<Material>;
    units: Collection<Unit>;
    categories: string[];
    trashed_count: number;
    filters: Filters;
}

export default function MaterialsIndex({ materials, units, categories, trashed_count, filters }: MaterialsPageProps) {
    const currency = usePage<SharedData>().props.app.currency;

    const [search, setSearch] = useState(filters.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Material | null>(null);
    const isFirstRender = useRef(true);

    const inTrash = filters.status === 'deleted';

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
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
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
                                <SelectItem value="deleted">Кошче{trashed_count > 0 ? ` (${trashed_count})` : ''}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {items.length === 0 ? (
                    <EmptyState
                        icon={inTrash ? Trash2 : Package}
                        title={inTrash ? 'Кошчето е празно' : search || filters.category ? 'Няма намерени материали' : 'Каталогът е празен'}
                        description={
                            inTrash
                                ? 'Тук попадат изтритите материали, които още участват в записани калкулации.'
                                : 'Добавете материалите, които използвате най-често, за да въвеждате калкулациите по-бързо.'
                        }
                        action={
                            inTrash ? undefined : (
                                <Button onClick={openCreate}>
                                    <Plus aria-hidden="true" /> Нов материал
                                </Button>
                            )
                        }
                    />
                ) : (
                    <>
                        <ul className="grid gap-3 md:hidden">
                            {items.map((material) => (
                                <li key={material.id} className="bg-card rounded-lg border p-4 shadow-xs">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{material.name}</p>
                                            <p className="text-muted-foreground truncate text-sm">
                                                {material.category || 'Без категория'}
                                                {material.sku ? ` · ${material.sku}` : ''}
                                            </p>
                                        </div>
                                        <p className="shrink-0 font-semibold tabular-nums">
                                            {formatUnitPrice(material.default_unit_price, currency)}
                                            <span className="text-muted-foreground text-xs font-normal">/{material.unit_symbol}</span>
                                        </p>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        {material.deleted_at ? (
                                            <Badge variant="destructive">В Кошчето</Badge>
                                        ) : (
                                            !material.is_active && <Badge variant="secondary">Архивиран</Badge>
                                        )}
                                        <div className="ml-auto flex items-center gap-2">
                                            {!material.deleted_at && (
                                                <>
                                                    <Button size="sm" variant="outline" onClick={() => openEdit(material)}>
                                                        <Pencil aria-hidden="true" /> Редакция
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => toggleArchive(material)}
                                                        aria-label={material.is_active ? 'Архивирай' : 'Активирай'}
                                                    >
                                                        {material.is_active ? <Archive aria-hidden="true" /> : <ArchiveRestore aria-hidden="true" />}
                                                    </Button>
                                                </>
                                            )}
                                            <MaterialTrashActions material={material} />
                                        </div>
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
                                                {material.deleted_at ? (
                                                    <Badge variant="destructive">В Кошчето</Badge>
                                                ) : material.is_active ? (
                                                    <Badge variant="outline">Активен</Badge>
                                                ) : (
                                                    <Badge variant="secondary">Архивиран</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-end gap-1">
                                                    {!material.deleted_at && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => openEdit(material)}
                                                                aria-label="Редакция"
                                                            >
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
                                                        </>
                                                    )}
                                                    <MaterialTrashActions material={material} compact />
                                                </div>
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

                <p className="text-muted-foreground text-xs">
                    Архивирането само скрива материала от търсенето. Изтриването го премахва от каталога: материал, който не участва в нито една
                    калкулация, изчезва веднага; използваният отива в Кошчето, за да останат записаните калкулации непроменени.{' '}
                    <Link href={route('calculations.create')} className="inline-flex items-center gap-1 underline">
                        <Calculator className="size-3" aria-hidden="true" /> Нова калкулация
                    </Link>
                </p>
            </div>

            <MaterialFormDialog open={dialogOpen} onOpenChange={setDialogOpen} units={units.data} material={editing} />
        </AppLayout>
    );
}

/**
 * Изтриване на един материал.
 *
 * Сървърът решава дали материалът да изчезне веднага, или да отиде в Кошчето.
 * Тук това решение само се обяснява предварително — според броя редове от
 * записани калкулации, които още сочат към материала.
 */
function MaterialTrashActions({ material, compact }: { material: Material; compact?: boolean }) {
    const uses = material.usage_count ?? 0;
    const name = `Материалът „${material.name}“`;
    const rows = `${uses} ${uses === 1 ? 'ред' : 'реда'}`;

    return (
        <TrashActions
            deletedAt={material.deleted_at}
            name={name}
            subject="материала"
            destroyUrl={route('materials.destroy', material.id)}
            restoreUrl={route('materials.restore', material.id)}
            forceUrl={route('materials.force-destroy', material.id)}
            compact={compact}
            confirmTitle={uses === 0 ? 'Изтриване на материала?' : 'Преместване в Кошчето?'}
            restoreDescription={<>{name} ще се върне в каталога и отново ще се предлага при въвеждане на калкулация.</>}
            confirmDescription={
                uses === 0 ? (
                    <>{name} не участва в нито една записана калкулация и ще бъде изтрит окончателно.</>
                ) : (
                    <>
                        {name} участва в {rows} от записани калкулации, затова отива в Кошчето вместо да бъде изтрит окончателно. Калкулациите остават
                        непроменени, а материалът може да бъде върнат по всяко време.
                    </>
                )
            }
            forceDescription={
                <>
                    {name} ще изчезне завинаги от базата данни.{' '}
                    {uses > 0 && <>Връзката към каталога отпада за {rows} от записани калкулации, но те запазват името, кода и цените си. </>}
                    Връщане след това няма да е възможно.
                </>
            }
        />
    );
}
