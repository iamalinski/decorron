import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney, formatUnitPrice } from '@/lib/money';
import type { Collection, SharedData } from '@/types';
import type { Calculation, Material } from '@/types/decorron';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Calculator, History, Package, Plus } from 'lucide-react';

interface DashboardProps {
    stats: {
        calculations_count: number;
        materials_count: number;
        last_calculated_at: string | null;
    };
    recentCalculations: Collection<Calculation>;
    frequentMaterials: Collection<Material>;
}

export default function Dashboard({ stats, recentCalculations, frequentMaterials }: DashboardProps) {
    const { auth, app } = usePage<SharedData>().props;
    const recent = recentCalculations.data;
    const materials = frequentMaterials.data;

    return (
        <AppLayout title="Табло" description={`Здравейте, ${auth.user.name}`}>
            <Head title="Табло" />

            <div className="mx-auto max-w-5xl space-y-5">
                <Button asChild className="h-16 w-full text-base shadow-sm">
                    <Link href={route('calculations.create')}>
                        <Plus className="size-5" aria-hidden="true" /> Нова калкулация
                    </Link>
                </Button>

                <div className="grid grid-cols-2 gap-3">
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Калкулации</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">{stats.calculations_count}</p>
                            {stats.last_calculated_at && (
                                <p className="mt-1 text-xs text-muted-foreground">последна {formatDate(stats.last_calculated_at)}</p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Материали в каталога</p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">{stats.materials_count}</p>
                            <Link href={route('materials.index')} className="mt-1 inline-flex items-center gap-1 text-xs underline">
                                Към каталога <ArrowRight className="size-3" aria-hidden="true" />
                            </Link>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                        <CardTitle className="text-base">Последни калкулации</CardTitle>
                        {recent.length > 0 && (
                            <Button asChild variant="ghost" size="sm">
                                <Link href={route('calculations.index')}>Виж всички</Link>
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {recent.length === 0 ? (
                            <EmptyState
                                icon={Calculator}
                                title="Още няма калкулации"
                                description="Въведете материалите, труда и желания марж — крайната цена се изчислява автоматично."
                                action={
                                    <Button asChild>
                                        <Link href={route('calculations.create')}>
                                            <Plus aria-hidden="true" /> Направи първата калкулация
                                        </Link>
                                    </Button>
                                }
                                className="border-0 py-6"
                            />
                        ) : (
                            <ul className="divide-y">
                                {recent.map((calculation) => (
                                    <li key={calculation.id}>
                                        <Link
                                            href={route('calculations.show', calculation.id)}
                                            className="flex items-center justify-between gap-3 py-3 transition-colors hover:bg-accent/40 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{calculation.service_name}</p>
                                                <p className="truncate text-sm text-muted-foreground">
                                                    {calculation.client_name || 'Без клиент'} · {formatDate(calculation.calculated_at)}
                                                </p>
                                            </div>
                                            <p className="shrink-0 font-semibold tabular-nums">
                                                {formatMoney(calculation.final_price, calculation.currency)}
                                            </p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                        <CardTitle className="text-base">Често използвани материали</CardTitle>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={route('materials.index')}>Каталог</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {materials.length === 0 ? (
                            <EmptyState
                                icon={Package}
                                title="Каталогът е празен"
                                description="Добавете материалите, които използвате често, за да въвеждате по-бързо."
                                action={
                                    <Button asChild variant="outline">
                                        <Link href={route('materials.index')}>Към материалите</Link>
                                    </Button>
                                }
                                className="border-0 py-6"
                            />
                        ) : (
                            <ul className="flex flex-wrap gap-2">
                                {materials.map((material) => (
                                    <li
                                        key={material.id}
                                        className="flex items-baseline gap-2 rounded-full border px-3 py-1.5 text-sm"
                                    >
                                        <span className="font-medium">{material.name}</span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {formatUnitPrice(material.default_unit_price, app.currency)}/{material.unit_symbol}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <p className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                    <History className="size-3" aria-hidden="true" />
                    Записаните калкулации пазят точните стойности от момента на изчислението.
                </p>
            </div>
        </AppLayout>
    );
}
