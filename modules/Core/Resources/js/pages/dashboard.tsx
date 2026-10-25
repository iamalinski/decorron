import { EmptyState } from '@/components/empty-state';
import { PipelineBars, type PipelineStage } from '@/components/pipeline-bars';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney, formatUnitPrice } from '@/lib/money';
import type { Collection, SharedData } from '@/types';
import type { Calculation, Material } from '@/types/decorron';
import { OrderStatusBadge } from '@modules/Crm/Resources/js/components/order-status-badge';
import { QuoteStatusBadge } from '@modules/Crm/Resources/js/components/quote-status-badge';
import { StageBadge } from '@modules/Crm/Resources/js/components/stage-badge';
import type { Opportunity, Order, Quote, Task } from '@modules/Crm/Resources/js/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarDays,
    Calculator,
    ClipboardList,
    FileText,
    History,
    Package,
    Plus,
    Target,
    UserPlus,
} from 'lucide-react';

interface DashboardProps {
    stats: {
        calculations_this_month: number;
        calculations_total: number;
        new_opportunities_this_month: number;
        open_opportunities: number;
        quotes_awaiting: number;
        won_this_month: number;
        upcoming_events: number;
        overdue_tasks: number;
        materials_count: number;
        committed_upcoming_total: string;
        currency: string;
    };
    pipeline: PipelineStage[];
    recentCalculations: Collection<Calculation>;
    recentOpportunities: Collection<Opportunity>;
    quotesAwaiting: Collection<Quote>;
    upcomingOrders: Collection<Order>;
    overdueTasks: Collection<Task>;
    opportunitiesWithoutNextAction: Collection<Opportunity>;
    frequentMaterials: Collection<Material>;
}

export default function Dashboard({
    stats,
    pipeline,
    recentCalculations,
    recentOpportunities,
    quotesAwaiting,
    upcomingOrders,
    overdueTasks,
    opportunitiesWithoutNextAction,
    frequentMaterials,
}: DashboardProps) {
    const { auth, app } = usePage<SharedData>().props;

    const attention = [
        ...overdueTasks.data.map((task) => ({
            key: `task-${task.id}`,
            url: route('crm.tasks.index', { status: 'overdue' }),
            title: task.title,
            subtitle: `Просрочена задача · срок ${task.due_on ? formatDate(task.due_on) : '—'}`,
        })),
        ...opportunitiesWithoutNextAction.data.map((opportunity) => ({
            key: `opportunity-${opportunity.id}`,
            url: route('crm.opportunities.show', opportunity.id),
            title: opportunity.title,
            subtitle: `${opportunity.customer_name ?? '—'} · без следващо действие`,
        })),
    ];

    return (
        <AppLayout title="Табло" description={`Здравейте, ${auth.user.name}`}>
            <Head title="Табло" />

            <div className="mx-auto max-w-5xl space-y-5">
                <Button asChild className="h-16 w-full text-base shadow-sm">
                    <Link href={route('calculations.create')}>
                        <Plus className="size-5" aria-hidden="true" /> Нова калкулация
                    </Link>
                </Button>

                <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm" variant="outline">
                        <Link href={route('crm.customers.index')}>
                            <UserPlus aria-hidden="true" /> Нов клиент
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <Link href={route('crm.opportunities.index')}>
                            <Target aria-hidden="true" /> Ново запитване
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <Link href={route('crm.quotes.create')}>
                            <FileText aria-hidden="true" /> Нова оферта
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <Link href={route('crm.tasks.index')}>
                            <ClipboardList aria-hidden="true" /> Задачи
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard
                        label="Калкулации този месец"
                        value={stats.calculations_this_month}
                        hint={`${stats.calculations_total} общо`}
                        icon={Calculator}
                        href={route('calculations.index')}
                    />
                    <StatCard
                        label="Нови запитвания"
                        value={stats.new_opportunities_this_month}
                        hint="този месец"
                        icon={Target}
                        href={route('crm.opportunities.index')}
                    />
                    <StatCard
                        label="Активни запитвания"
                        value={stats.open_opportunities}
                        icon={Target}
                        href={route('crm.opportunities.index')}
                    />
                    <StatCard
                        label="Оферти без отговор"
                        value={stats.quotes_awaiting}
                        icon={FileText}
                        href={route('crm.quotes.index', { status: 'sent' })}
                    />
                    <StatCard label="Спечелени" value={stats.won_this_month} hint="този месец" />
                    <StatCard
                        label="Предстоящи събития"
                        value={stats.upcoming_events}
                        hint="следващите 30 дни"
                        icon={CalendarDays}
                        href={route('crm.calendar')}
                    />
                    <StatCard
                        label="Просрочени задачи"
                        value={stats.overdue_tasks}
                        icon={AlertTriangle}
                        tone="warning"
                        href={route('crm.tasks.index', { status: 'overdue' })}
                    />
                    <StatCard
                        label="Материали в каталога"
                        value={stats.materials_count}
                        icon={Package}
                        href={route('materials.index')}
                    />
                </div>

                <Card>
                    <CardContent className="pt-5 pb-5">
                        <p className="text-muted-foreground text-xs tracking-wide uppercase">
                            Договорена стойност на предстоящите поръчки
                        </p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">
                            {formatMoney(stats.committed_upcoming_total, stats.currency)}
                        </p>
                        <p className="text-muted-foreground mt-1 text-xs">
                            Сумата е договорена по поръчки през следващите 30 дни. Това не е получен приход — отчитането на
                            плащания не е част от тази версия.
                        </p>
                    </CardContent>
                </Card>

                {attention.length > 0 && (
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <AlertTriangle className="text-destructive size-4" aria-hidden="true" /> Изисква внимание
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-2">
                                {attention.slice(0, 8).map((item) => (
                                    <li key={item.key}>
                                        <Link
                                            href={item.url}
                                            className="hover:bg-accent/40 focus-visible:ring-ring block rounded-lg border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                        >
                                            <p className="truncate text-sm font-medium">{item.title}</p>
                                            <p className="text-muted-foreground truncate text-xs">{item.subtitle}</p>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                        <CardTitle className="text-base">Запитвания по етапи</CardTitle>
                        <Button asChild size="sm" variant="ghost">
                            <Link href={route('crm.overview')}>CRM табло</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <PipelineBars stages={pipeline} hrefFor={(stage) => route('crm.opportunities.index', { stage })} />
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <ListCard
                        title="Последни запитвания"
                        href={route('crm.opportunities.index')}
                        empty="Още няма отворени запитвания."
                        items={recentOpportunities.data.map((opportunity) => ({
                            key: opportunity.id,
                            url: route('crm.opportunities.show', opportunity.id),
                            title: opportunity.title,
                            subtitle: opportunity.customer_name ?? '—',
                            right: <StageBadge stage={opportunity.stage} label={opportunity.stage_label} />,
                        }))}
                    />

                    <ListCard
                        title="Оферти, чакащи отговор"
                        href={route('crm.quotes.index', { status: 'sent' })}
                        empty="Няма изпратени оферти без отговор."
                        items={quotesAwaiting.data.map((quote) => ({
                            key: quote.id,
                            url: route('crm.quotes.show', quote.id),
                            title: quote.customer_name,
                            subtitle: `${quote.quote_number}${quote.valid_until ? ` · валидна до ${formatDate(quote.valid_until)}` : ''}`,
                            right: <QuoteStatusBadge status={quote.status} label={quote.status_label} />,
                        }))}
                    />

                    <ListCard
                        title="Предстоящи събития"
                        href={route('crm.calendar')}
                        empty="Няма предстоящи поръчки с дата."
                        items={upcomingOrders.data.map((order) => ({
                            key: order.id,
                            url: route('crm.orders.show', order.id),
                            title: order.customer_name,
                            subtitle: `${order.event_date ? formatDate(order.event_date) : '—'} · ${formatMoney(order.agreed_total, order.currency)}`,
                            right: <OrderStatusBadge status={order.status} label={order.status_label} />,
                        }))}
                    />

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                            <CardTitle className="text-base">Последни калкулации</CardTitle>
                            <Button asChild size="sm" variant="ghost">
                                <Link href={route('calculations.index')}>Всички</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {recentCalculations.data.length === 0 ? (
                                <EmptyState
                                    icon={History}
                                    title="Още няма калкулации"
                                    description="Първата калкулация отнема по-малко от минута."
                                    action={
                                        <Button asChild size="sm">
                                            <Link href={route('calculations.create')}>
                                                <Plus aria-hidden="true" /> Нова калкулация
                                            </Link>
                                        </Button>
                                    }
                                    className="py-8"
                                />
                            ) : (
                                <ul className="grid gap-2">
                                    {recentCalculations.data.map((calculation) => (
                                        <li key={calculation.id}>
                                            <Link
                                                href={route('calculations.show', calculation.id)}
                                                className="hover:bg-accent/40 focus-visible:ring-ring flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">{calculation.service_name}</p>
                                                    <p className="text-muted-foreground truncate text-xs">
                                                        {calculation.client_name || 'Без клиент'} ·{' '}
                                                        {formatDate(calculation.calculated_at)}
                                                    </p>
                                                </div>
                                                <span className="shrink-0 font-semibold tabular-nums">
                                                    {formatMoney(calculation.final_price, calculation.currency)}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {frequentMaterials.data.length > 0 && (
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                            <CardTitle className="text-base">Често използвани материали</CardTitle>
                            <Button asChild size="sm" variant="ghost">
                                <Link href={route('materials.index')}>Каталог</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <ul className="flex flex-wrap gap-2">
                                {frequentMaterials.data.map((material) => (
                                    <li
                                        key={material.id}
                                        className="text-muted-foreground rounded-full border px-3 py-1.5 text-xs"
                                    >
                                        {material.name}
                                        <span className="ml-1.5 tabular-nums">
                                            {formatUnitPrice(material.default_unit_price)} {app.currency}/{material.unit_symbol ?? ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

interface ListItem {
    key: number;
    url: string;
    title: string;
    subtitle: string;
    right?: React.ReactNode;
}

function ListCard({ title, href, items, empty }: { title: string; href: string; items: ListItem[]; empty: string }) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                <CardTitle className="text-base">{title}</CardTitle>
                <Button asChild size="sm" variant="ghost">
                    <Link href={href}>Всички</Link>
                </Button>
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{empty}</p>
                ) : (
                    <ul className="grid gap-2">
                        {items.map((item) => (
                            <li key={item.key}>
                                <Link
                                    href={item.url}
                                    className="hover:bg-accent/40 focus-visible:ring-ring flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">{item.title}</p>
                                        <p className="text-muted-foreground truncate text-xs">{item.subtitle}</p>
                                    </div>
                                    {item.right && <div className="shrink-0">{item.right}</div>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
