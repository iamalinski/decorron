import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Loader2, Plus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { Customer } from '../types';

interface CustomerPickerProps {
    /** Предварително заредени клиенти (най-скорошните). */
    customers: Customer[];
    /** Избраният клиент, ако е известен. */
    value: Customer | null;
    onSelect: (customer: Customer) => void;
    /** Отваря диалога за нов клиент с подаденото име. */
    onCreateRequest?: (name: string) => void;
    id?: string;
    placeholder?: string;
    /** Позволява изчистване на избора. */
    onClear?: () => void;
}

/**
 * Autocomplete за клиенти. Търси на сървъра с debounce, за да не тегли
 * целия списък, и предлага бързо създаване на нов клиент.
 */
export function CustomerPicker({ customers, value, onSelect, onCreateRequest, onClear, id, placeholder }: CustomerPickerProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Customer[] | null>(null);
    const [loading, setLoading] = useState(false);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const term = query.trim();

        if (term.length < 2) {
            setResults(null);
            setLoading(false);

            return;
        }

        setLoading(true);
        const timer = window.setTimeout(() => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            fetch(`${route('crm.customers.search')}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : Promise.reject(response)))
                .then((body) => setResults(body.customers ?? []))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);

        return () => window.clearTimeout(timer);
    }, [query, open]);

    const options = useMemo(() => {
        if (results !== null) {
            return results;
        }

        const term = query.trim().toLowerCase();

        if (term === '') {
            return customers;
        }

        return customers.filter(
            (customer) => customer.name.toLowerCase().includes(term) || (customer.phone ?? '').includes(term),
        );
    }, [results, query, customers]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className={cn('h-11 w-full justify-between font-normal', !value && 'text-muted-foreground')}
                >
                    <span className="truncate">{value?.name ?? placeholder ?? 'Изберете клиент'}</span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] min-w-72 p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput placeholder="Търсене по име или телефон…" value={query} onValueChange={setQuery} />
                    <CommandList>
                        {loading && (
                            <div className="text-muted-foreground flex items-center justify-center gap-2 py-6 text-sm">
                                <Loader2 className="size-4 animate-spin" aria-hidden="true" /> Търсене…
                            </div>
                        )}

                        {!loading && options.length === 0 && <CommandEmpty>Няма намерени клиенти.</CommandEmpty>}

                        {!loading && options.length > 0 && (
                            <CommandGroup heading="Клиенти">
                                {options.map((customer) => (
                                    <CommandItem
                                        key={customer.id}
                                        value={String(customer.id)}
                                        onSelect={() => {
                                            onSelect(customer);
                                            setOpen(false);
                                            setQuery('');
                                        }}
                                    >
                                        <Check
                                            className={cn('size-4', value?.id === customer.id ? 'opacity-100' : 'opacity-0')}
                                            aria-hidden="true"
                                        />
                                        <span className="flex-1 truncate">{customer.name}</span>
                                        {customer.phone && <span className="text-muted-foreground shrink-0 text-xs">{customer.phone}</span>}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        )}

                        <CommandGroup heading="Друго">
                            {onCreateRequest && (
                                <CommandItem
                                    value="--create"
                                    onSelect={() => {
                                        onCreateRequest(query.trim());
                                        setOpen(false);
                                    }}
                                >
                                    <Plus className="size-4" aria-hidden="true" />
                                    <span className="truncate">
                                        {query.trim() === '' ? 'Нов клиент' : `Добави „${query.trim()}“ като нов клиент`}
                                    </span>
                                </CommandItem>
                            )}
                            {onClear && value && (
                                <CommandItem
                                    value="--clear"
                                    onSelect={() => {
                                        onClear();
                                        setOpen(false);
                                        setQuery('');
                                    }}
                                >
                                    Без клиент
                                </CommandItem>
                            )}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
