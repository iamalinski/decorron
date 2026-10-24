import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { Collection } from '@/types';
import type { Unit } from '@/types/decorron';
import { Head, router, usePage } from '@inertiajs/react';
import { Archive, ArchiveRestore, Pencil, Plus, Ruler } from 'lucide-react';
import { useEffect, useState } from 'react';

interface UnitsPageProps {
    units: Collection<Unit>;
    filters: { status: 'active' | 'archived' | 'all' };
}

export default function UnitsIndex({ units, filters }: UnitsPageProps) {
    const errors = usePage().props.errors as Record<string, string>;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Unit | null>(null);
    const [name, setName] = useState('');
    const [symbol, setSymbol] = useState('');
    const [description, setDescription] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (!dialogOpen) {
            return;
        }

        setName(editing?.name ?? '');
        setSymbol(editing?.symbol ?? '');
        setDescription(editing?.description ?? '');
    }, [dialogOpen, editing]);

    const openCreate = () => {
        setEditing(null);
        setDialogOpen(true);
    };

    const openEdit = (unit: Unit) => {
        setEditing(unit);
        setDialogOpen(true);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (processing) {
            return;
        }

        setProcessing(true);

        const payload = { name: name.trim(), symbol: symbol.trim(), description: description.trim() || null, is_active: editing?.is_active ?? true };

        const options = {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(route('units.update', editing.id), payload, options);
        } else {
            router.post(route('units.store'), payload, options);
        }
    };

    const toggleArchive = (unit: Unit) => {
        router.patch(route('units.archive', unit.id), {}, { preserveScroll: true });
    };

    const items = units.data;

    return (
        <AppLayout
            title="Мерни единици"
            description="Използват се в материалите и в калкулациите"
            actions={
                <Button size="sm" onClick={openCreate}>
                    <Plus aria-hidden="true" /> <span className="hidden sm:inline">Нова единица</span>
                </Button>
            }
        >
            <Head title="Мерни единици" />

            <div className="mx-auto max-w-4xl space-y-4">
                <Select
                    value={filters.status}
                    onValueChange={(value) => router.get(route('units.index'), { status: value }, { preserveState: true })}
                >
                    <SelectTrigger className="h-11 w-full sm:w-48" aria-label="Статус">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">Активни</SelectItem>
                        <SelectItem value="archived">Архивирани</SelectItem>
                        <SelectItem value="all">Всички</SelectItem>
                    </SelectContent>
                </Select>

                {items.length === 0 ? (
                    <EmptyState
                        icon={Ruler}
                        title="Няма мерни единици"
                        description="Добавете мерните единици, с които работите — например бр., м. или комплект."
                        action={
                            <Button onClick={openCreate}>
                                <Plus aria-hidden="true" /> Нова единица
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <ul className="grid gap-3 sm:hidden">
                            {items.map((unit) => (
                                <li key={unit.id} className="flex items-center gap-3 rounded-lg border bg-card p-4 shadow-xs">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {unit.name} <span className="text-muted-foreground">({unit.symbol})</span>
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {unit.materials_count ?? 0} материала{!unit.is_active && ' · архивирана'}
                                        </p>
                                    </div>
                                    <Button size="sm" variant="ghost" onClick={() => openEdit(unit)} aria-label="Редакция">
                                        <Pencil />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => toggleArchive(unit)}
                                        aria-label={unit.is_active ? 'Архивирай' : 'Активирай'}
                                    >
                                        {unit.is_active ? <Archive /> : <ArchiveRestore />}
                                    </Button>
                                </li>
                            ))}
                        </ul>

                        <Card className="hidden sm:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Име</TableHead>
                                        <TableHead>Символ</TableHead>
                                        <TableHead>Описание</TableHead>
                                        <TableHead className="text-right">Материали</TableHead>
                                        <TableHead>Статус</TableHead>
                                        <TableHead className="text-right">Действия</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((unit) => (
                                        <TableRow key={unit.id}>
                                            <TableCell className="font-medium">{unit.name}</TableCell>
                                            <TableCell>{unit.symbol}</TableCell>
                                            <TableCell className="text-muted-foreground">{unit.description || '—'}</TableCell>
                                            <TableCell className="text-right tabular-nums">{unit.materials_count ?? 0}</TableCell>
                                            <TableCell>
                                                {unit.is_active ? <Badge variant="outline">Активна</Badge> : <Badge variant="secondary">Архивирана</Badge>}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button size="sm" variant="ghost" onClick={() => openEdit(unit)} aria-label="Редакция">
                                                    <Pencil />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => toggleArchive(unit)}
                                                    aria-label={unit.is_active ? 'Архивирай' : 'Активирай'}
                                                >
                                                    {unit.is_active ? <Archive /> : <ArchiveRestore />}
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                    </>
                )}

                <p className="text-xs text-muted-foreground">
                    Архивирането не поврежда записаните калкулации — те пазят името и символа на мерната единица към момента на изчислението. В тази
                    версия няма автоматично преобразуване между единици (например см. и м.).
                </p>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Редакция на мерна единица' : 'Нова мерна единица'}</DialogTitle>
                        <DialogDescription>Символът трябва да е уникален, защото се показва в калкулациите.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="unit-name">Име *</Label>
                            <Input id="unit-name" value={name} onChange={(e) => setName(e.target.value)} required autoFocus placeholder="напр. Метър" />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="unit-symbol">Кратък символ *</Label>
                            <Input id="unit-symbol" value={symbol} onChange={(e) => setSymbol(e.target.value)} required placeholder="напр. м." />
                            <InputError message={errors.symbol} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="unit-description">Описание</Label>
                            <Input
                                id="unit-description"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                placeholder="по желание"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setDialogOpen(false)}>
                                Отказ
                            </Button>
                            <Button type="submit" disabled={processing || !name.trim() || !symbol.trim()}>
                                {editing ? 'Запази промените' : 'Добави'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
