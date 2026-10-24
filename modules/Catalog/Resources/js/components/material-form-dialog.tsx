import InputError from '@/components/input-error';
import { NumericInput } from '@/components/numeric-input';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Material, Unit } from '@/types/decorron';
import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

type Errors = Record<string, string>;

export interface MaterialFormValues {
    name: string;
    sku: string;
    category: string;
    unit_id: string;
    default_unit_price: string;
    description: string;
}

interface MaterialFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    units: Unit[];
    /** Материал за редакция; липсва при създаване. */
    material?: Material | null;
    /** Начално име (напр. текстът, който потребителят е написал в търсачката). */
    initialName?: string;
    /**
     * Когато е подадено, диалогът праща JSON заявка и връща създадения материал,
     * вместо да презарежда страницата. Използва се от екрана за калкулация.
     */
    onCreated?: (material: Material) => void;
}

const emptyValues: MaterialFormValues = {
    name: '',
    sku: '',
    category: '',
    unit_id: '',
    default_unit_price: '',
    description: '',
};

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function MaterialFormDialog({ open, onOpenChange, units, material, initialName, onCreated }: MaterialFormDialogProps) {
    const [values, setValues] = useState<MaterialFormValues>(emptyValues);
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);

    const isEditing = Boolean(material);

    useEffect(() => {
        if (!open) {
            return;
        }

        setErrors({});
        setValues(
            material
                ? {
                      name: material.name,
                      sku: material.sku ?? '',
                      category: material.category ?? '',
                      unit_id: String(material.unit_id),
                      default_unit_price: material.default_unit_price,
                      description: material.description ?? '',
                  }
                : {
                      ...emptyValues,
                      name: initialName ?? '',
                      unit_id: units[0] ? String(units[0].id) : '',
                  },
        );
    }, [open, material, initialName, units]);

    const set = <K extends keyof MaterialFormValues>(key: K, value: MaterialFormValues[K]) => {
        setValues((current) => ({ ...current, [key]: value }));
    };

    const payload = () => ({
        name: values.name.trim(),
        sku: values.sku.trim() || null,
        category: values.category.trim() || null,
        unit_id: values.unit_id ? Number(values.unit_id) : null,
        default_unit_price: values.default_unit_price === '' ? '0' : values.default_unit_price,
        description: values.description.trim() || null,
        is_active: true,
    });

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();

        if (processing) {
            return;
        }

        setProcessing(true);
        setErrors({});

        // Бързо добавяне от екрана за калкулация: JSON, без презареждане.
        if (onCreated && !isEditing) {
            try {
                const response = await fetch(route('materials.store'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(payload()),
                });

                if (response.status === 422) {
                    const body = await response.json();
                    const flat: Errors = {};
                    Object.entries(body.errors ?? {}).forEach(([key, messages]) => {
                        flat[key] = (messages as string[])[0];
                    });
                    setErrors(flat);

                    return;
                }

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const body = await response.json();
                onCreated(body.material);
                toast.success(`Материалът „${body.material.name}“ е добавен в каталога.`);
                onOpenChange(false);
            } catch {
                toast.error('Материалът не беше запазен. Проверете връзката и опитайте отново.');
            } finally {
                setProcessing(false);
            }

            return;
        }

        const options = {
            preserveScroll: true,
            onError: (serverErrors: Errors) => setErrors(serverErrors),
            onSuccess: () => onOpenChange(false),
            onFinish: () => setProcessing(false),
        };

        if (isEditing && material) {
            router.patch(route('materials.update', material.id), payload(), options);
        } else {
            router.post(route('materials.store'), payload(), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{isEditing ? 'Редакция на материал' : 'Нов материал'}</DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Промените важат само за нови калкулации. Записаните калкулации запазват старите стойности.'
                            : 'Материалът се добавя в каталога и може да се използва веднага.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="material-name">Име *</Label>
                        <Input
                            id="material-name"
                            value={values.name}
                            onChange={(e) => set('name', e.target.value)}
                            required
                            autoFocus
                            placeholder="напр. Балони хром"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="material-unit">Мерна единица *</Label>
                            <Select value={values.unit_id} onValueChange={(value) => set('unit_id', value)}>
                                <SelectTrigger id="material-unit">
                                    <SelectValue placeholder="Изберете" />
                                </SelectTrigger>
                                <SelectContent>
                                    {units.map((unit) => (
                                        <SelectItem key={unit.id} value={String(unit.id)}>
                                            {unit.name} ({unit.symbol})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.unit_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="material-price">Стандартна единична цена</Label>
                            <NumericInput
                                id="material-price"
                                value={values.default_unit_price}
                                onValueChange={(value) => set('default_unit_price', value)}
                                suffix="EUR"
                                placeholder="0,00"
                            />
                            <InputError message={errors.default_unit_price} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="material-sku">Код (SKU)</Label>
                            <Input id="material-sku" value={values.sku} onChange={(e) => set('sku', e.target.value)} placeholder="по желание" />
                            <InputError message={errors.sku} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="material-category">Категория</Label>
                            <Input
                                id="material-category"
                                value={values.category}
                                onChange={(e) => set('category', e.target.value)}
                                placeholder="по желание"
                            />
                            <InputError message={errors.category} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="material-description">Описание</Label>
                        <Textarea
                            id="material-description"
                            value={values.description}
                            onChange={(e) => set('description', e.target.value)}
                            rows={2}
                            placeholder="по желание"
                        />
                        <InputError message={errors.description} />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Отказ
                        </Button>
                        <Button type="submit" disabled={processing || !values.name.trim() || !values.unit_id}>
                            {processing && <Loader2 className="animate-spin" aria-hidden="true" />}
                            {isEditing ? 'Запази промените' : 'Добави материал'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
