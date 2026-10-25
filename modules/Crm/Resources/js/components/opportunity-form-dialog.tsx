import InputError from '@/components/input-error';
import { NumericInput } from '@/components/numeric-input';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CustomerFormDialog } from './customer-form-dialog';
import { CustomerPicker } from './customer-picker';
import {
    emptyOpportunityForm,
    opportunityToForm,
    type CrmOptions,
    type Customer,
    type Opportunity,
    type OpportunityFormValues,
} from '../types';

interface OpportunityFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: CrmOptions;
    /** Предварително заредени клиенти за autocomplete. */
    customers: Customer[];
    /** Запитване за редакция; липсва при създаване. */
    opportunity?: Opportunity | null;
    /** Фиксиран клиент — напр. когато се създава от профила му. */
    lockedCustomer?: Customer | null;
}

const NO_SOURCE = '__none__';

export function OpportunityFormDialog({
    open,
    onOpenChange,
    options,
    customers,
    opportunity,
    lockedCustomer,
}: OpportunityFormDialogProps) {
    const [values, setValues] = useState<OpportunityFormValues>(emptyOpportunityForm());
    const [selected, setSelected] = useState<Customer | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [newCustomerName, setNewCustomerName] = useState<string | null>(null);

    const isEditing = Boolean(opportunity);

    useEffect(() => {
        if (!open) {
            return;
        }

        setErrors({});

        if (opportunity) {
            setValues(opportunityToForm(opportunity));
            setSelected(customers.find((customer) => customer.id === opportunity.customer_id) ?? null);

            return;
        }

        setValues(emptyOpportunityForm(lockedCustomer?.id));
        setSelected(lockedCustomer ?? null);
    }, [open, opportunity, lockedCustomer, customers]);

    const set = (key: keyof OpportunityFormValues, value: string) => {
        setValues((current) => ({ ...current, [key]: value }));
    };

    const pickCustomer = (customer: Customer) => {
        setSelected(customer);
        set('customer_id', String(customer.id));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        const done = {
            onError: (bag: Record<string, string>) => setErrors(bag),
            onSuccess: () => onOpenChange(false),
            onFinish: () => setProcessing(false),
            preserveScroll: true,
        };

        if (opportunity) {
            router.patch(route('crm.opportunities.update', opportunity.id), values, done);
        } else {
            router.post(route('crm.opportunities.store'), values, done);
        }
    };

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{isEditing ? 'Редакция на запитване' : 'Ново запитване'}</DialogTitle>
                        <DialogDescription>
                            Запитването описва едно конкретно събитие. Попълнете само това, което вече знаете.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="opportunity-customer">Клиент</Label>
                            {lockedCustomer ? (
                                <Input id="opportunity-customer" value={lockedCustomer.name} readOnly className="h-11" />
                            ) : (
                                <CustomerPicker
                                    id="opportunity-customer"
                                    customers={customers}
                                    value={selected}
                                    onSelect={pickCustomer}
                                    onCreateRequest={(name) => setNewCustomerName(name)}
                                />
                            )}
                            <InputError message={errors.customer_id} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="opportunity-title">Заглавие</Label>
                            <Input
                                id="opportunity-title"
                                value={values.title}
                                onChange={(event) => set('title', event.target.value)}
                                placeholder="Напр. Балонена украса за 7-и рожден ден"
                                className="h-11"
                                required
                                maxLength={160}
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="opportunity-event-type">Вид събитие</Label>
                                <Input
                                    id="opportunity-event-type"
                                    list="crm-event-types"
                                    value={values.event_type}
                                    onChange={(event) => set('event_type', event.target.value)}
                                    className="h-11"
                                    maxLength={60}
                                />
                                <datalist id="crm-event-types">
                                    {options.event_types.map((option) => (
                                        <option key={option.value} value={option.value} />
                                    ))}
                                </datalist>
                                <InputError message={errors.event_type} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="opportunity-event-date">Дата на събитието</Label>
                                <Input
                                    id="opportunity-event-date"
                                    type="date"
                                    value={values.event_date}
                                    onChange={(event) => set('event_date', event.target.value)}
                                    className="h-11"
                                />
                                <InputError message={errors.event_date} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="opportunity-location">Място</Label>
                            <Input
                                id="opportunity-location"
                                value={values.event_location}
                                onChange={(event) => set('event_location', event.target.value)}
                                className="h-11"
                                maxLength={255}
                            />
                            <InputError message={errors.event_location} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="opportunity-budget">Очакван бюджет (EUR)</Label>
                                <NumericInput
                                    id="opportunity-budget"
                                    value={values.expected_budget}
                                    onValueChange={(value) => set('expected_budget', value)}
                                    className="h-11"
                                />
                                <InputError message={errors.expected_budget} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="opportunity-source">Източник на запитването</Label>
                                <Select
                                    value={values.source === '' ? NO_SOURCE : values.source}
                                    onValueChange={(value) => set('source', value === NO_SOURCE ? '' : value)}
                                >
                                    <SelectTrigger id="opportunity-source" className="h-11">
                                        <SelectValue placeholder="Не е посочен" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_SOURCE}>Не е посочен</SelectItem>
                                        {options.sources.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.source} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="opportunity-stage">Етап</Label>
                            <Select value={values.stage} onValueChange={(value) => set('stage', value)}>
                                <SelectTrigger id="opportunity-stage" className="h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.stages.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.stage} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label htmlFor="opportunity-next-action">Следващо действие</Label>
                                <Input
                                    id="opportunity-next-action"
                                    value={values.next_action}
                                    onChange={(event) => set('next_action', event.target.value)}
                                    placeholder="Напр. Да се обадя за уточнение"
                                    className="h-11"
                                    maxLength={255}
                                />
                                <InputError message={errors.next_action} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="opportunity-next-due">Срок</Label>
                                <Input
                                    id="opportunity-next-due"
                                    type="date"
                                    value={values.next_action_due}
                                    onChange={(event) => set('next_action_due', event.target.value)}
                                    className="h-11"
                                />
                                <InputError message={errors.next_action_due} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="opportunity-notes">Вътрешни бележки</Label>
                            <Textarea
                                id="opportunity-notes"
                                value={values.notes}
                                onChange={(event) => set('notes', event.target.value)}
                                rows={3}
                                maxLength={5000}
                            />
                            <InputError message={errors.notes} />
                        </div>

                        <DialogFooter className="gap-2">
                            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                                Отказ
                            </Button>
                            <Button type="submit" disabled={processing || values.title.trim() === '' || values.customer_id === ''}>
                                {processing && <Loader2 className="animate-spin" aria-hidden="true" />}
                                {isEditing ? 'Запази промените' : 'Създай запитване'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <CustomerFormDialog
                open={newCustomerName !== null}
                onOpenChange={(value) => !value && setNewCustomerName(null)}
                options={options}
                initialName={newCustomerName ?? ''}
                onCreated={(customer) => {
                    pickCustomer(customer);
                    setNewCustomerName(null);
                }}
            />
        </>
    );
}
