import { PipelineBars, type PipelineStage } from '@/components/pipeline-bars';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatMoney } from '@/lib/money';
import type { Collection } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CalendarDays, ClipboardList, FileText, Plus, Target, Users } from 'lucide-react';
import { OrderStatusBadge } from '../components/order-status-badge';
import { QuoteStatusBadge } from '../components/quote-status-badge';
import { StageBadge } from '../components/stage-badge';
import type { Opportunity, Order, Quote, Task } from '../types';

interface OverviewProps {
    stats: {
        customers: number;
        open_opportunities: number;
        won_this_month: number;
        lost_this_month: number;
        quotes_awaiting: number;
        active_orders: number;
        overdue_tasks: number;
    };
    pipeline: PipelineStage[];
    sources: { source: string; label: string; count: number }[];
    recentOpportunities: Collection<Opportunity>;
    quotesAwaiting: Collection<Quote>;
    upcomingOrders: Collection<Order>;
    overdueTasks: Collection<Task>;
}

export default function CrmOverview({
    stats,
    pipeline,
    sources,
    recentOpportunities,
    quotesAwaiting,
    upcomingOrders,
    overdueTasks,
}: OverviewProps) {
    const sourceMax = Math.max(1, ...sources.map((source) => source.count));

    return (
        <AppLayout title="CRM табло" description="Клиенти, запитвания и продажби">
            <Head title="CRM табло" />

            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm">
                        <Link href={route('crm.opportunities.index')}>
                            <Plus aria-hidden="true" /> Ново запитване
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <Link href={route('crm.customers.index')}>
                            <Users aria-hidden="true" /> Клиенти
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="outline">
                        <Link href={route('crm.quotes.create')}>
                            <FileText aria-hidden="true" /> Нова оферта
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard label="Клиенти" value={stats.customers} icon={Users} href={route('crm.customers.index')} />
                    <StatCard
                        label="Отворени запитвания"
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
                    <StatCard
                        label="Просрочени задачи"
                        value={stats.overdue_tasks}
                        icon={AlertTriangle}
                        tone="warning"
                        href={route('crm.tasks.index', { status: 'overdue' })}
                    />
                    <StatCard
                        label="Активни поръчки"
                        value={stats.active_orders}
                        icon={ClipboardList}
                        href={route('crm.orders.index')}
                    />
                    <StatCard label="Спечелени този месец" value={stats.won_this_month} />
                    <StatCard label="Загубени този месец" value={stats.lost_this_month} />
                    <StatCard label="Календар" value="→" icon={CalendarDays} href={route('crm.calendar')} />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Запитвания по етапи</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PipelineBars stages={pipeline} hrefFor={(stage) => route('crm.opportunities.index', { stage })} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Източници на запитванията</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {sources.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Още няма запитвания с посочен източник.</p>
                            ) : (
                                <ul className="grid gap-2">
                                    {sources.map((source) => (
                                        <li key={source.source} className="flex items-center gap-2">
                                            <span className="w-28 shrink-0 truncate text-xs">{source.label}</span>
                                            <span className="bg-muted h-5 flex-1 overflow-hidden rounded">
                                                <span
                                                    className="bg-primary/70 block h-full rounded"
                                                    style={{ width: `${Math.round((source.count / sourceMax) * 100)}%` }}
                                                />
                                            </span>
                                            <span className="w-6 shrink-0 text-right text-xs tabular-nums">{source.count}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {overdueTasks.data.length > 0 && (
                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
                            <CardTitle className="text-base">Изисква внимание</CardTitle>
                            <Button asChild size="sm" variant="ghost">
                                <Link href={route('crm.tasks.index', { status: 'overdue' })}>Всички</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-2">
                                {overdueTasks.data.map((task) => (
                                    <li key={task.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">{task.title}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {task.customer_name ?? task.type_label}
                                            </p>
                                        </div>
                                        <span className="text-destructive shrink-0 text-xs font-medium">
                                            срок {task.due_on ? formatDate(task.due_on) : '—'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

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
                </div>
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
