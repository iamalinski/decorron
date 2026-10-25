import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatDate } from '@/lib/money';
import { Loader2, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CustomerFormDialog } from './customer-form-dialog';
import { CustomerPicker } from './customer-picker';
import type { CrmOptions, Customer, Opportunity } from '../types';

/** Какво да се направи в CRM при запазване на калкулацията. */
export interface CrmLinkValue {
    customer_id: number | null;
    opportunity_id: number | null;
    create_opportunity: boolean;
    /** Свободен текст, когато клиентът не е от CRM. */
    client_name: string;
}

interface CrmLinkCardProps {
    options: CrmOptions;
    customers: Customer[];
    value: CrmLinkValue;
    onChange: (value: CrmLinkValue) => void;
    /** Предварително избран клиент (напр. при дублиране или от запитване). */
    presetCustomer?: Customer | null;
    /** Предварително избрано запитване — зачита се само при първо зареждане. */
    presetOpportunityId?: number | null;
    errors: Record<string, string>;
}

const NO_OPPORTUNITY = '__none__';
const NEW_OPPORTUNITY = '__new__';

/**
 * Избор на клиент и запитване за калкулацията.
 *
 * Нищо не се създава в CRM, докато калкулацията не бъде записана, и винаги е
 * видимо какво ще се случи — няма скрито създаване на запитвания.
 */
export function CrmLinkCard({ options, customers, value, onChange, presetCustomer, presetOpportunityId, errors }: CrmLinkCardProps) {
    const [customer, setCustomer] = useState<Customer | null>(presetCustomer ?? null);
    const [open, setOpen] = useState<Opportunity[]>([]);
    const [loading, setLoading] = useState(false);
    const [newCustomerName, setNewCustomerName] = useState<string | null>(null);
    const pendingPreset = useRef<number | null>(presetOpportunityId ?? null);

    // Отворените запитвания на избрания клиент се зареждат при нужда.
    useEffect(() => {
        if (customer === null) {
            setOpen([]);

            return;
        }

        let cancelled = false;
        setLoading(true);

        fetch(route('crm.customers.opportunities', customer.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((body) => {
                if (cancelled) {
                    return;
                }

                const list: Opportunity[] = body.opportunities ?? [];
                setOpen(list);

                // Предварително избраното запитване (дублиране, идване от CRM)
                // има предимство пред автоматичния избор.
                const preset = pendingPreset.current;
                pendingPreset.current = null;

                if (preset !== null && list.some((item) => item.id === preset)) {
                    onChange({
                        customer_id: customer.id,
                        opportunity_id: preset,
                        create_opportunity: false,
                        client_name: customer.name,
                    });

                    return;
                }

                // Сценарий А: точно едно отворено запитване → предлага се то.
                // Сценарий Б: няма такова → предлага се създаване на ново.
                onChange({
                    customer_id: customer.id,
                    opportunity_id: list.length === 1 ? list[0].id : null,
                    create_opportunity: list.length === 0,
                    client_name: customer.name,
                });
            })
            .catch(() => undefined)
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [customer]);

    const selection = value.opportunity_id !== null ? String(value.opportunity_id) : value.create_opportunity ? NEW_OPPORTUNITY : NO_OPPORTUNITY;

    const pickSelection = (next: string) => {
        onChange({
            ...value,
            opportunity_id: next === NEW_OPPORTUNITY || next === NO_OPPORTUNITY ? null : Number(next),
            create_opportunity: next === NEW_OPPORTUNITY,
        });
    };

    const clearCustomer = () => {
        setCustomer(null);
        onChange({ customer_id: null, opportunity_id: null, create_opportunity: false, client_name: '' });
    };

    return (
        <>
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-base">Клиент и CRM</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="crm-customer">Клиент от CRM</Label>
                        <div className="flex gap-2">
                            <div className="min-w-0 flex-1">
                                <CustomerPicker
                                    id="crm-customer"
                                    customers={customers}
                                    value={customer}
                                    onSelect={setCustomer}
                                    onCreateRequest={(name) => setNewCustomerName(name)}
                                    placeholder="Без клиент"
                                />
                            </div>
                            {customer && (
                                <Button type="button" variant="outline" size="icon" className="h-11 w-11" onClick={clearCustomer} aria-label="Премахни клиента">
                                    <X />
                                </Button>
                            )}
                        </div>
                        <InputError message={errors.customer_id} />
                    </div>

                    {customer === null && (
                        <div className="grid gap-2">
                            <Label htmlFor="client_name">Име на клиента (без CRM запис)</Label>
                            <Input
                                id="client_name"
                                value={value.client_name}
                                onChange={(event) => onChange({ ...value, client_name: event.target.value })}
                                placeholder="по желание"
                                className="h-11"
                            />
                            <p className="text-muted-foreground text-xs">
                                Калкулацията се записва в историята, без да създава клиент или запитване в CRM.
                            </p>
                            <InputError message={errors.client_name} />
                        </div>
                    )}

                    {customer !== null && (
                        <div className="grid gap-2">
                            <Label htmlFor="crm-opportunity">Запитване</Label>
                            {loading ? (
                                <p className="text-muted-foreground flex items-center gap-2 py-2 text-sm">
                                    <Loader2 className="size-4 animate-spin" aria-hidden="true" /> Проверка на отворените запитвания…
                                </p>
                            ) : (
                                <Select value={selection} onValueChange={pickSelection}>
                                    <SelectTrigger id="crm-opportunity" className="h-11">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {open.map((opportunity) => (
                                            <SelectItem key={opportunity.id} value={String(opportunity.id)}>
                                                {opportunity.title}
                                                {opportunity.event_date ? ` · ${formatDate(opportunity.event_date)}` : ''}
                                            </SelectItem>
                                        ))}
                                        <SelectItem value={NEW_OPPORTUNITY}>Създай ново запитване</SelectItem>
                                        <SelectItem value={NO_OPPORTUNITY}>Без запитване засега</SelectItem>
                                    </SelectContent>
                                </Select>
                            )}
                            <p className="text-muted-foreground text-xs">
                                {selection === NEW_OPPORTUNITY
                                    ? 'Ще бъде създадено ново запитване на етап „Изготвяне на цена“.'
                                    : selection === NO_OPPORTUNITY
                                      ? 'Калкулацията ще се запише само за клиента. Можете да я свържете по-късно.'
                                      : 'Калкулацията ще бъде добавена към избраното запитване.'}
                            </p>
                            <InputError message={errors.opportunity_id} />
                        </div>
                    )}
                </CardContent>
            </Card>

            <CustomerFormDialog
                open={newCustomerName !== null}
                onOpenChange={(next) => !next && setNewCustomerName(null)}
                options={options}
                initialName={newCustomerName ?? ''}
                onCreated={(created) => {
                    setCustomer(created);
                    setNewCustomerName(null);
                }}
            />
        </>
    );
}
