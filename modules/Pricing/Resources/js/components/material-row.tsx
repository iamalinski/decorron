import { NumericInput } from '@/components/numeric-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatMoney, formatUnitPrice } from '@/lib/money';
import type { LineResult } from '@/lib/pricing';
import type { Material, Unit } from '@/types/decorron';
import { MaterialPicker } from '@modules/Catalog/Resources/js/components/material-picker';
import { Trash2 } from 'lucide-react';
import type { LineState } from './line-types';

interface MaterialRowProps {
    line: LineState;
    index: number;
    units: Unit[];
    materials: Material[];
    computed: LineResult;
    currency: string;
    canRemove: boolean;
    /** Влиза ли редът в сумата (има име и количество). */
    counted: boolean;
    onChange: (patch: Partial<LineState>) => void;
    onRemove: () => void;
    onCreateMaterial: (name: string) => void;
}

export function MaterialRow({
    line,
    index,
    units,
    materials,
    computed,
    currency,
    canRemove,
    counted,
    onChange,
    onRemove,
    onCreateMaterial,
}: MaterialRowProps) {
    const unit = units.find((candidate) => String(candidate.id) === line.unit_id);
    const isTotalMode = line.input_mode === 'total';
    const rowId = `row-${line.key}`;

    const selectMaterial = (material: Material) => {
        onChange({
            material_id: material.id,
            material_name: material.name,
            sku: material.sku,
            unit_id: String(material.unit_id),
            // Стандартната цена от каталога е само начална стойност —
            // промяната ѝ тук не засяга каталога.
            input_mode: 'unit',
            unit_price: material.default_unit_price,
            quantity: line.quantity === '' ? '1' : line.quantity,
        });
    };

    return (
        <li className="bg-card rounded-lg border p-3 shadow-xs">
            <div className="flex items-start gap-2">
                <div className="min-w-0 flex-1">
                    <Label htmlFor={rowId} className="sr-only">
                        Материал {index + 1}
                    </Label>
                    <MaterialPicker
                        id={rowId}
                        materials={materials}
                        value={line.material_name}
                        onSelect={selectMaterial}
                        onFreeText={(name) => onChange({ material_id: null, sku: null, material_name: name })}
                        onCreateRequest={onCreateMaterial}
                    />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground hover:text-destructive h-11 w-11 shrink-0"
                    onClick={onRemove}
                    disabled={!canRemove}
                    aria-label={`Премахни ред ${index + 1}`}
                >
                    <Trash2 />
                </Button>
            </div>

            <div className="mt-3">
                <ToggleGroup
                    type="single"
                    value={line.input_mode}
                    onValueChange={(value) => value && onChange({ input_mode: value as LineState['input_mode'] })}
                    className="grid w-full grid-cols-2 rounded-md border p-0.5"
                    aria-label="Как въвеждате цената"
                >
                    <ToggleGroupItem value="total" className="data-[state=on]:bg-accent h-9 rounded-sm text-xs">
                        Обща стойност
                    </ToggleGroupItem>
                    <ToggleGroupItem value="unit" className="data-[state=on]:bg-accent h-9 rounded-sm text-xs">
                        Единична цена
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div className="grid gap-1.5">
                    <Label htmlFor={`${rowId}-qty`} className="text-muted-foreground text-xs">
                        Количество
                    </Label>
                    <NumericInput
                        id={`${rowId}-qty`}
                        value={line.quantity}
                        onValueChange={(value) => onChange({ quantity: value })}
                        placeholder="0"
                        className="h-11"
                    />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor={`${rowId}-unit`} className="text-muted-foreground text-xs">
                        Мярка
                    </Label>
                    <Select value={line.unit_id} onValueChange={(value) => onChange({ unit_id: value })}>
                        <SelectTrigger id={`${rowId}-unit`} className="h-11">
                            <SelectValue placeholder="—" />
                        </SelectTrigger>
                        <SelectContent>
                            {units.map((candidate) => (
                                <SelectItem key={candidate.id} value={String(candidate.id)}>
                                    {candidate.symbol}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="col-span-2 grid gap-1.5">
                    <Label htmlFor={`${rowId}-price`} className="text-muted-foreground text-xs">
                        {isTotalMode ? 'Обща стойност' : `Цена за 1 ${unit?.symbol ?? ''}`.trim()}
                    </Label>
                    <NumericInput
                        id={`${rowId}-price`}
                        value={isTotalMode ? line.entered_total_cost : line.unit_price}
                        onValueChange={(value) => onChange(isTotalMode ? { entered_total_cost: value } : { unit_price: value })}
                        suffix={currency}
                        placeholder="0,00"
                        className="h-11"
                    />
                </div>
            </div>

            <dl className="mt-3 flex items-center justify-between gap-3 border-t pt-2 text-sm">
                <div className="flex items-baseline gap-1.5">
                    <dt className="text-muted-foreground text-xs">{isTotalMode ? 'Единична цена' : 'Обща стойност'}</dt>
                    <dd className="font-medium tabular-nums">
                        {isTotalMode ? (
                            <>
                                {formatUnitPrice(computed.unitPrice, currency)}
                                <span className="text-muted-foreground text-xs">/{unit?.symbol ?? ''}</span>
                            </>
                        ) : (
                            formatMoney(computed.lineTotal, currency)
                        )}
                    </dd>
                </div>
                <div className="flex items-baseline gap-1.5">
                    <dt className="text-muted-foreground text-xs">Ред</dt>
                    <dd className="font-semibold tabular-nums">{formatMoney(computed.lineTotal, currency)}</dd>
                </div>
            </dl>

            {!counted && (line.material_name.trim() !== '' || line.quantity.trim() !== '') && (
                <p className="mt-2 text-xs text-amber-600 dark:text-amber-500">
                    Редът още не влиза в сумата — попълнете име на материала и количество, по-голямо от нула.
                </p>
            )}
        </li>
    );
}
