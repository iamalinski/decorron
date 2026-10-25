import { TrashActions, TrashBanner } from '@/components/trash-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/money';
import type { Collection } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArchiveRestore, ArchiveX, ArrowLeft, Mail, MapPin, Pencil, Phone, Plus, Target } from 'lucide-react';
import { useState } from 'react';
import { CustomerFormDialog } from '../../components/customer-form-dialog';
import { OpportunityFormDialog } from '../../components/opportunity-form-dialog';
import { StageBadge, StatusBadge } from '../../components/stage-badge';
import type { CrmOptions, Customer, Opportunity } from '../../types';

interface CustomerShowProps {
    customer: { data: Customer };
    opportunities: Collection<Opportunity>;
    options: CrmOptions;
}

export default function CustomerShow({ customer: wrapper, opportunities, options }: CustomerShowProps) {
    const customer = wrapper.data;
    const [editing, setEditing] = useState(false);
    const [newOpportunity, setNewOpportunity] = useState(false);

    const isTrashed = Boolean(customer.deleted_at);

    const toggleArchive = () => {
        router.patch(route('crm.customers.archive', customer.id), {}, { preserveScroll: true });
    };

    return (
        <AppLayout
            title={customer.name}
            description={[customer.type_label, customer.city].filter(Boolean).join(' · ')}
            actions={
                <>
                    {!isTrashed && (
                        <>
                            <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                                <Pencil aria-hidden="true" /> <span className="hidden sm:inline">Редакция</span>
                            </Button>
                            <Button size="sm" variant="outline" onClick={toggleArchive}>
                                {customer.is_archived ? <ArchiveRestore aria-hidden="true" /> : <ArchiveX aria-hidden="true" />}
                                <span className="hidden sm:inline">{customer.is_archived ? 'Активирай' : 'Архивирай'}</span>
                            </Button>
                        </>
                    )}
                    <TrashActions
                        deletedAt={customer.deleted_at}
                        blockers={customer.deletion_blockers}
                        blockerUrl={blockerUrlFor(customer.id)}
                        name={`Клиентът „${customer.name}“`}
                        subject="клиента"
                        destroyUrl={route('crm.customers.destroy', customer.id)}
                        restoreUrl={route('crm.customers.restore', customer.id)}
                        forceUrl={route('crm.customers.force-destroy', customer.id)}
                    />
                </>
            }
        >
            <Head title={customer.name} />

            <div className="mx-auto max-w-3xl space-y-4">
                <Button asChild variant="ghost" size="sm" className="-ml-2">
                    <Link href={route('crm.customers.index')}>
                        <ArrowLeft aria-hidden="true" /> Към клиентите
                    </Link>
                </Button>

                {isTrashed && (
                    <TrashBanner>
                        Този клиент е в Кошчето и не се показва в списъците, таблото и календара. Можете да го
                        върнете или да го изтриете окончателно.
                    </TrashBanner>
                )}

                {customer.is_archived && !isTrashed && (
                    <Badge variant="outline" className="text-muted-foreground">
                        Архивиран клиент
                    </Badge>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Контактна информация</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                        <Field label="Телефон" icon={Phone}>
                            {customer.phone ? (
                                <a href={`tel:${customer.phone}`} className="hover:underline">
                                    {customer.phone}
                                </a>
                            ) : (
                                '—'
                            )}
                        </Field>

                        <Field label="Имейл" icon={Mail}>
                            {customer.email ? (
                                <a href={`mailto:${customer.email}`} className="break-all hover:underline">
                                    {customer.email}
                                </a>
                            ) : (
                                '—'
                            )}
                        </Field>

                        {customer.contact_person && <Field label="Лице за контакт">{customer.contact_person}</Field>}

                        <Field label="Адрес" icon={MapPin}>
                            {[customer.city, customer.address].filter(Boolean).join(', ') || '—'}
                        </Field>

                        <Field label="Източник">{customer.source_label ?? '—'}</Field>

                        <Field label="Добавен на">{customer.created_at ? formatDate(customer.created_at) : '—'}</Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle className="text-base">Запитвания</CardTitle>
                        <Button size="sm" variant="outline" onClick={() => setNewOpportunity(true)}>
                            <Plus aria-hidden="true" /> Ново запитване
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {opportunities.data.length === 0 ? (
                            <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                <Target className="size-4" aria-hidden="true" />
                                Още няма запитвания за този клиент.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {opportunities.data.map((opportunity) => (
                                    <li key={opportunity.id}>
                                        <Link
                                            href={route('crm.opportunities.show', opportunity.id)}
                                            className="hover:bg-accent/40 focus-visible:ring-ring flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">{opportunity.title}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {opportunity.event_date ? formatDate(opportunity.event_date) : 'Без дата'}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-1.5">
                                                <StageBadge stage={opportunity.stage} label={opportunity.stage_label} />
                                                <StatusBadge status={opportunity.status} label={opportunity.status_label} />
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Вътрешни бележки</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {customer.notes ? (
                            <p className="text-sm whitespace-pre-wrap">{customer.notes}</p>
                        ) : (
                            <p className="text-muted-foreground text-sm">Няма бележки.</p>
                        )}
                    </CardContent>
                </Card>

                {(customer.deletion_blockers?.length ?? 0) > 0 && (
                    <p className="text-muted-foreground px-1 text-xs">
                        Клиентът има свързани записи (
                        {customer.deletion_blockers?.map((blocker) => `${blocker.count} ${blocker.label}`).join(', ')}
                        ), затова не може да бъде изтрит веднага. Изтрийте ги едно по едно, или архивирайте клиента,
                        ако искате историята му да остане четима.
                    </p>
                )}
            </div>

            <CustomerFormDialog open={editing} onOpenChange={setEditing} options={options} customer={customer} />

            <OpportunityFormDialog
                open={newOpportunity}
                onOpenChange={setNewOpportunity}
                options={options}
                customers={[customer]}
                lockedCustomer={customer}
            />
        </AppLayout>
    );
}

/** Къде да отиде потребителят, за да изтрие пречещите записи. */
function blockerUrlFor(customerId: number): (key: string) => string | null {
    return (key) => {
        switch (key) {
            case 'opportunities':
                return route('crm.opportunities.index', { customer_id: customerId, status: 'all' });
            case 'quotes':
                return route('crm.quotes.index', { customer_id: customerId });
            case 'orders':
                return route('crm.orders.index', { customer_id: customerId, status: 'all' });
            case 'calculations':
                return route('calculations.index', { customer_id: customerId });
            case 'tasks':
                return route('crm.tasks.index', { customer_id: customerId, status: 'all' });
            default:
                return null;
        }
    };
}

function Field({ label, icon: Icon, children }: { label: string; icon?: React.ElementType; children: React.ReactNode }) {
    return (
        <div>
            <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
                {Icon && <Icon className="size-3.5" aria-hidden="true" />}
                {label}
            </p>
            <p className="mt-0.5">{children}</p>
        </div>
    );
}
