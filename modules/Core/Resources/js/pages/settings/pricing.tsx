import InputError from '@/components/input-error';
import { NumericInput } from '@/components/numeric-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatPercent } from '@/lib/money';
import type { TaxRates } from '@/lib/pricing';
import { Head, router } from '@inertiajs/react';
import { Info, Lock } from 'lucide-react';
import { useState } from 'react';

interface PricingSettingsProps {
    settings: {
        default_net_margin_percent: number;
        default_rounding_strategy: string;
    };
    taxes: TaxRates;
    errors: Record<string, string>;
}

const ROUNDING_LABELS: Record<string, string> = {
    none: 'Без закръгляване',
    integer: 'До цяло евро',
    ends_5: 'До кръгла сума (на 5)',
    ends_9: 'До цена на 9',
};

export default function PricingSettings({ settings, taxes, errors }: PricingSettingsProps) {
    const [margin, setMargin] = useState(String(settings.default_net_margin_percent));
    const [rounding, setRounding] = useState(settings.default_rounding_strategy);
    const [processing, setProcessing] = useState(false);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        router.patch(
            route('settings.pricing.update'),
            { default_net_margin_percent: margin || '0', default_rounding_strategy: rounding },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    const maxMarginPercent = (Number(taxes.max_net_margin) * 100).toFixed(2);

    return (
        <AppLayout title="Настройки" description="Ценообразуване">
            <Head title="Настройки" />

            <SettingsLayout>
            <div className="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Стойности по подразбиране</CardTitle>
                        <CardDescription>
                            Важат само за нови калкулации. Вече записаните калкулации не се променят при промяна на тези настройки.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="default_margin">Желан чист марж по подразбиране</Label>
                                <NumericInput
                                    id="default_margin"
                                    value={margin}
                                    onValueChange={setMargin}
                                    suffix="%"
                                    className="h-11 w-32"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Чиста печалба след данъци като процент от крайната цена. Трябва да е под {maxMarginPercent}%.
                                </p>
                                <InputError message={errors.default_net_margin_percent} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="default_rounding">Предложение за закръгляване</Label>
                                <Select value={rounding} onValueChange={setRounding}>
                                    <SelectTrigger id="default_rounding" className="h-11 w-full sm:w-64">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(ROUNDING_LABELS).map(([value, label]) => (
                                            <SelectItem key={value} value={value}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.default_rounding_strategy} />
                            </div>

                            <div>
                                <Button type="submit" disabled={processing}>
                                    Запази настройките
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Lock className="size-4" aria-hidden="true" /> Данъчни ставки
                        </CardTitle>
                        <CardDescription>Фиксирани в кода на приложението и недостъпни за редакция от интерфейса.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <dl className="divide-y text-sm">
                            <div className="flex justify-between py-2">
                                <dt>Корпоративен данък</dt>
                                <dd className="font-medium tabular-nums">{formatPercent(taxes.corporate, 0)}</dd>
                            </div>
                            <div className="flex justify-between py-2">
                                <dt>Данък върху дивидента</dt>
                                <dd className="font-medium tabular-nums">{formatPercent(taxes.dividend, 0)}</dd>
                            </div>
                            <div className="flex justify-between py-2">
                                <dt>ДДС</dt>
                                <dd className="font-medium tabular-nums">{formatPercent(taxes.vat, 0)}</dd>
                            </div>
                            <div className="flex justify-between py-2">
                                <dt>Остава от печалбата след двата данъка</dt>
                                <dd className="font-medium tabular-nums">{formatPercent(taxes.net_factor, 2)}</dd>
                            </div>
                        </dl>

                        <Alert>
                            <Info className="size-4" aria-hidden="true" />
                            <AlertTitle>Опростен модел</AlertTitle>
                            <AlertDescription>
                                Приложението приема, че всички въведени разходи са признати и че целият остатък след корпоративния данък се
                                разпределя като дивидент. Личните осигуровки не се включват автоматично — отчитайте ги в разходите. Това не замества
                                индивидуална счетоводна консултация.
                            </AlertDescription>
                        </Alert>
                    </CardContent>
                </Card>
            </div>
            </SettingsLayout>
        </AppLayout>
    );
}
