import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { customerToForm, emptyCustomerForm, type CrmOptions, type Customer, type CustomerFormValues } from '../types';

interface CustomerFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: CrmOptions;
    /** Клиент за редакция; липсва при създаване. */
    customer?: Customer | null;
    /** Начално име — напр. текстът, въведен в търсачката. */
    initialName?: string;
    /**
     * Когато е подадено, диалогът праща JSON заявка и връща създадения клиент,
     * вместо да навигира към профила му. Използва се при бързо добавяне.
     */
    onCreated?: (customer: Customer) => void;
}

const NO_SOURCE = '__none__';

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function CustomerFormDialog({ open, onOpenChange, options, customer, initialName, onCreated }: CustomerFormDialogProps) {
    const [values, setValues] = useState<CustomerFormValues>(emptyCustomerForm());
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const isEditing = Boolean(customer);
    const duplicateWarning = errors.duplicate;

    useEffect(() => {
        if (!open) {
            return;
        }

        setErrors({});
        setValues(customer ? customerToForm(customer) : { ...emptyCustomerForm(), name: initialName ?? '' });
    }, [open, customer, initialName]);

    const set = <K extends keyof CustomerFormValues>(key: K, value: CustomerFormValues[K]) => {
        setValues((current) => ({ ...current, [key]: value }));
    };

    const submit = (event: React.FormEvent, confirmDuplicate = false) => {
        event.preventDefault();
        setProcessing(true);

        const payload = { ...values, confirm_duplicate: confirmDuplicate };

        if (onCreated && !customer) {
            fetch(route('crm.customers.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(payload),
            })
                .then(async (response) => {
                    const body = await response.json();

                    if (response.status === 422) {
                        setErrors(
                            Object.fromEntries(
                                Object.entries(body.errors ?? {}).map(([key, messages]) => [key, (messages as string[])[0]]),
                            ),
                        );

                        return;
                    }

                    if (!response.ok) {
                        setErrors({ name: 'Клиентът не можа да бъде записан. Опитайте отново.' });

                        return;
                    }

                    onCreated(body.customer as Customer);
                    onOpenChange(false);
                })
                .catch(() => setErrors({ name: 'Няма връзка със сървъра. Опитайте отново.' }))
                .finally(() => setProcessing(false));

            return;
        }

        const done = {
            onError: (bag: Record<string, string>) => setErrors(bag),
            onSuccess: () => onOpenChange(false),
            onFinish: () => setProcessing(false),
            preserveScroll: true,
        };

        if (customer) {
            router.patch(route('crm.customers.update', customer.id), payload, done);
        } else {
            router.post(route('crm.customers.store'), payload, done);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Редакция на клиент' : 'Нов клиент'}</DialogTitle>
                    <DialogDescription>
                        Задължително е само името. Останалите полета попълнете, когато разполагате с информацията.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={(event) => submit(event)} className="grid gap-4">
                    {duplicateWarning && (
                        <Alert variant="destructive">
                            <AlertTriangle aria-hidden="true" />
                            <AlertTitle>Възможен дубликат</AlertTitle>
                            <AlertDescription className="space-y-2">
                                <span>{duplicateWarning}</span>
                                <Button type="button" size="sm" variant="outline" onClick={(event) => submit(event, true)}>
                                    Това е различен клиент — запази
                                </Button>
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="grid gap-1.5">
                        <Label htmlFor="customer-type">Тип клиент</Label>
                        <Select value={values.type} onValueChange={(value) => set('type', value)}>
                            <SelectTrigger id="customer-type" className="h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.customer_types.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="customer-name">{values.type === 'organization' ? 'Име на организацията' : 'Име и фамилия'}</Label>
                        <Input
                            id="customer-name"
                            value={values.name}
                            onChange={(event) => set('name', event.target.value)}
                            className="h-11"
                            autoFocus
                            required
                            maxLength={160}
                        />
                        <InputError message={errors.name} />
                    </div>

                    {values.type === 'organization' && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="customer-contact">Лице за контакт</Label>
                            <Input
                                id="customer-contact"
                                value={values.contact_person}
                                onChange={(event) => set('contact_person', event.target.value)}
                                className="h-11"
                                maxLength={160}
                            />
                            <InputError message={errors.contact_person} />
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="customer-phone">Телефон</Label>
                            <Input
                                id="customer-phone"
                                type="tel"
                                inputMode="tel"
                                value={values.phone}
                                onChange={(event) => set('phone', event.target.value)}
                                className="h-11"
                                maxLength={40}
                            />
                            <InputError message={errors.phone} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="customer-email">Имейл</Label>
                            <Input
                                id="customer-email"
                                type="email"
                                inputMode="email"
                                value={values.email}
                                onChange={(event) => set('email', event.target.value)}
                                className="h-11"
                                maxLength={160}
                            />
                            <InputError message={errors.email} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="customer-city">Град</Label>
                            <Input
                                id="customer-city"
                                value={values.city}
                                onChange={(event) => set('city', event.target.value)}
                                className="h-11"
                                maxLength={120}
                            />
                            <InputError message={errors.city} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="customer-source">Източник</Label>
                            <Select
                                value={values.source === '' ? NO_SOURCE : values.source}
                                onValueChange={(value) => set('source', value === NO_SOURCE ? '' : value)}
                            >
                                <SelectTrigger id="customer-source" className="h-11">
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
                        <Label htmlFor="customer-address">Адрес</Label>
                        <Input
                            id="customer-address"
                            value={values.address}
                            onChange={(event) => set('address', event.target.value)}
                            className="h-11"
                            maxLength={255}
                        />
                        <InputError message={errors.address} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="customer-notes">Вътрешни бележки</Label>
                        <Textarea
                            id="customer-notes"
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
                        <Button type="submit" disabled={processing || values.name.trim() === ''}>
                            {processing && <Loader2 className="animate-spin" aria-hidden="true" />}
                            {isEditing ? 'Запази промените' : 'Добави клиента'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
