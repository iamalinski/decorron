import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatUnitPrice } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Material } from '@/types/decorron';
import { Check, ChevronsUpDown, Loader2, Plus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

interface MaterialPickerProps {
    /** Материали, заредени предварително (най-използваните). */
    materials: Material[];
    value: string;
    /** Избор от каталога. */
    onSelect: (material: Material) => void;
    /** Свободен текст — материал извън каталога. */
    onFreeText: (name: string) => void;
    /** Отваря диалога за нов материал с подаденото име. */
    onCreateRequest: (name: string) => void;
    id?: string;
}

/**
 * Autocomplete за каталога с материали.
 *
 * Търси на сървъра с debounce, за да не се зарежда целият каталог,
 * и позволява материал извън каталога или бързо добавяне на нов.
 */
export function MaterialPicker({ materials, value, onSelect, onFreeText, onCreateRequest, id }: MaterialPickerProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Material[] | null>(null);
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

            fetch(`${route('materials.search')}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : Promise.reject(response)))
                .then((body) => setResults(body.materials ?? []))
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
            return materials;
        }

        return materials.filter((material) => material.name.toLowerCase().includes(term) || (material.sku ?? '').toLowerCase().includes(term));
    }, [results, query, materials]);

    const commit = (material: Material) => {
        onSelect(material);
        setOpen(false);
        setQuery('');
    };

    const useAsFreeText = () => {
        const name = query.trim();

        if (name !== '') {
            onFreeText(name);
        }

        setOpen(false);
        setQuery('');
    };

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
                    <span className="truncate">{value || 'Изберете или въведете материал'}</span>
                    <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] min-w-72 p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput placeholder="Търсене в каталога…" value={query} onValueChange={setQuery} />
                    <CommandList>
                        {loading && (
                            <div className="flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" aria-hidden="true" /> Търсене…
                            </div>
                        )}

                        {!loading && options.length === 0 && <CommandEmpty>Няма намерени материали.</CommandEmpty>}

                        {!loading && options.length > 0 && (
                            <CommandGroup heading="Каталог">
                                {options.map((material) => (
                                    <CommandItem key={material.id} value={String(material.id)} onSelect={() => commit(material)}>
                                        <Check className={cn('size-4', value === material.name ? 'opacity-100' : 'opacity-0')} aria-hidden="true" />
                                        <span className="flex-1 truncate">{material.name}</span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {formatUnitPrice(material.default_unit_price)}/{material.unit_symbol ?? ''}
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        )}

                        {query.trim() !== '' && (
                            <CommandGroup heading="Друго">
                                <CommandItem value="--free-text" onSelect={useAsFreeText}>
                                    <span className="truncate">
                                        Използвай „<strong>{query.trim()}</strong>“ само за тази калкулация
                                    </span>
                                </CommandItem>
                                <CommandItem
                                    value="--create"
                                    onSelect={() => {
                                        onCreateRequest(query.trim());
                                        setOpen(false);
                                    }}
                                >
                                    <Plus className="size-4" aria-hidden="true" />
                                    <span className="truncate">Добави „{query.trim()}“ в каталога</span>
                                </CommandItem>
                            </CommandGroup>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
