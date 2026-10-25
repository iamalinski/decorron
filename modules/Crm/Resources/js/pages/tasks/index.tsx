import { EmptyState } from '@/components/empty-state';
import { TrashActions } from '@/components/trash-actions';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDate, today } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Collection, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ListChecks, Loader2, Plus } from 'lucide-react';
import { useState } from 'react';
import { CustomerPicker } from '../../components/customer-picker';
import type { CrmOptions, Customer, Task } from '../../types';

interface TasksIndexProps {
    tasks: Paginated<Task>;
    filters: { status: string };
    options: CrmOptions;
    customers: Collection<Customer>;
}

export default function TasksIndex({ tasks, filters, options, customers }: TasksIndexProps) {
    const [dialogOpen, setDialogOpen] = useState(false);

    const items = tasks.data;

    return (
        <AppLayout
            title="Задачи"
            description={`${tasks.meta.total} ${tasks.meta.total === 1 ? 'задача' : 'задачи'}`}
            actions={
                <Button size="sm" onClick={() => setDialogOpen(true)}>
                    <Plus aria-hidden="true" /> <span className="hidden sm:inline">Нова задача</span>
                </Button>
            }
        >
            <Head title="Задачи" />

            <div className="mx-auto max-w-3xl space-y-4">
                <Select
                    value={filters.status}
                    onValueChange={(value) =>
                        router.get(route('crm.tasks.index'), value === 'open' ? {} : { status: value }, { preserveState: true })
                    }
                >
                    <SelectTrigger className="h-11 w-52" aria-label="Филтър по състояние">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="open">Отворени</SelectItem>
                        <SelectItem value="overdue">Просрочени</SelectItem>
                        <SelectItem value="completed">Изпълнени</SelectItem>
                        <SelectItem value="all">Всички</SelectItem>
                        <SelectItem value="deleted">Кошче</SelectItem>
                    </SelectContent>
                </Select>

                {items.length === 0 ? (
                    <EmptyState
                        icon={ListChecks}
                        title="Няма задачи"
                        description="Задачите са напомняния вътре в приложението — обаждане, изпращане на оферта, подготовка на материали."
                        action={
                            <Button onClick={() => setDialogOpen(true)}>
                                <Plus aria-hidden="true" /> Нова задача
                            </Button>
                        }
                    />
                ) : (
                    <Card>
                        <CardContent className="divide-y p-0">
                            {items.map((task) => (
                                <TaskRow key={task.id} task={task} />
                            ))}
                        </CardContent>
                    </Card>
                )}

                {tasks.meta.last_page > 1 && (
                    <nav className="flex flex-wrap items-center justify-center gap-1" aria-label="Страници">
                        {tasks.links.map((link, index) => (
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
            </div>

            <TaskDialog open={dialogOpen} onOpenChange={setDialogOpen} options={options} customers={customers.data} />
        </AppLayout>
    );
}

function TaskRow({ task }: { task: Task }) {
    const done = task.completed_at !== null;

    return (
        <div className="flex items-start gap-3 p-4">
            <Checkbox
                checked={done}
                onCheckedChange={() => router.patch(route('crm.tasks.toggle', task.id), {}, { preserveScroll: true })}
                aria-label={done ? `Върни „${task.title}“ в работа` : `Отбележи „${task.title}“ като изпълнена`}
                className="mt-1"
            />

            <div className="min-w-0 flex-1">
                <p className={cn('font-medium', done && 'text-muted-foreground line-through')}>{task.title}</p>

                {task.description && <p className="text-muted-foreground mt-0.5 text-sm">{task.description}</p>}

                <div className="text-muted-foreground mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                    <Badge variant="secondary">{task.type_label}</Badge>

                    {task.due_on && (
                        <span className={cn(task.is_overdue && 'text-destructive font-medium')}>
                            {task.is_overdue ? 'Просрочена: ' : 'Срок: '}
                            {formatDate(task.due_on)}
                        </span>
                    )}

                    {task.customer_id && (
                        <Link href={route('crm.customers.show', task.customer_id)} className="hover:underline">
                            {task.customer_name}
                        </Link>
                    )}

                    {task.crm_opportunity_id && (
                        <Link href={route('crm.opportunities.show', task.crm_opportunity_id)} className="hover:underline">
                            {task.opportunity_title}
                        </Link>
                    )}
                </div>
            </div>

            <div className="flex shrink-0 gap-1">
                <TrashActions
                    compact
                    deletedAt={task.deleted_at}
                    name={`Задачата „${task.title}“`}
                    subject="задачата"
                    destroyUrl={route('crm.tasks.destroy', task.id)}
                    restoreUrl={route('crm.tasks.restore', task.id)}
                    forceUrl={route('crm.tasks.force-destroy', task.id)}
                />
            </div>
        </div>
    );
}

function TaskDialog({
    open,
    onOpenChange,
    options,
    customers,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: CrmOptions;
    customers: Customer[];
}) {
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [type, setType] = useState('call');
    const [dueOn, setDueOn] = useState(today());
    const [customer, setCustomer] = useState<Customer | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        router.post(
            route('crm.tasks.store'),
            {
                title,
                description: description.trim() || null,
                type,
                due_on: dueOn || null,
                customer_id: customer?.id ?? null,
            },
            {
                preserveScroll: true,
                onError: setErrors,
                onSuccess: () => {
                    setTitle('');
                    setDescription('');
                    setCustomer(null);
                    onOpenChange(false);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Нова задача</DialogTitle>
                    <DialogDescription>Напомнянето се показва в приложението — не се изпраща имейл или SMS.</DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="task-title">Какво трябва да се направи</Label>
                        <Input
                            id="task-title"
                            value={title}
                            onChange={(event) => setTitle(event.target.value)}
                            className="h-11"
                            maxLength={160}
                            autoFocus
                            required
                        />
                        <InputError message={errors.title} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="task-type">Вид</Label>
                            <Select value={type} onValueChange={setType}>
                                <SelectTrigger id="task-type" className="h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.task_types.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="task-due">Срок</Label>
                            <Input
                                id="task-due"
                                type="date"
                                value={dueOn}
                                onChange={(event) => setDueOn(event.target.value)}
                                className="h-11"
                            />
                            <InputError message={errors.due_on} />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="task-customer">Клиент (по избор)</Label>
                        <CustomerPicker
                            id="task-customer"
                            customers={customers}
                            value={customer}
                            onSelect={setCustomer}
                            onClear={() => setCustomer(null)}
                            placeholder="Без клиент"
                        />
                        <InputError message={errors.customer_id} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="task-description">Бележка</Label>
                        <Textarea
                            id="task-description"
                            value={description}
                            onChange={(event) => setDescription(event.target.value)}
                            rows={2}
                            maxLength={2000}
                        />
                        <InputError message={errors.description} />
                    </div>

                    <DialogFooter className="gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                            Отказ
                        </Button>
                        <Button type="submit" disabled={processing || title.trim() === ''}>
                            {processing && <Loader2 className="animate-spin" aria-hidden="true" />}
                            Добави
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
