import InputError from '@/components/input-error';
import { NumericInput } from '@/components/numeric-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Head, router, usePage } from '@inertiajs/react';
import { Loader2, Save } from 'lucide-react';
import { useState } from 'react';

interface CompanySettingsProps {
    settings: {
        company_name: string | null;
        company_phone: string | null;
        company_email: string | null;
        company_address: string | null;
        company_extra: string | null;
        default_quote_terms: string | null;
        default_quote_validity_days: number;
    };
    fallbackName: string;
}

export default function CompanySettings({ settings, fallbackName }: CompanySettingsProps) {
    const errors = usePage().props.errors as Record<string, string>;

    const [values, setValues] = useState({
        company_name: settings.company_name ?? '',
        company_phone: settings.company_phone ?? '',
        company_email: settings.company_email ?? '',
        company_address: settings.company_address ?? '',
        company_extra: settings.company_extra ?? '',
        default_quote_terms: settings.default_quote_terms ?? '',
        default_quote_validity_days: String(settings.default_quote_validity_days),
    });
    const [processing, setProcessing] = useState(false);

    const set = (key: keyof typeof values, value: string) => setValues((current) => ({ ...current, [key]: value }));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        router.patch(route('settings.company.update'), values, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AppLayout title="Данни на бизнеса" description="Използват се в офертите и поръчките">
            <Head title="Данни на бизнеса" />

            <SettingsLayout>
                <form onSubmit={submit} className="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Данни за клиентските документи</CardTitle>
                            <CardDescription>
                                Показват се в горната част на офертата. Ако името е празно, се използва „{fallbackName}“.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="company_name">Име на бизнеса</Label>
                                <Input
                                    id="company_name"
                                    value={values.company_name}
                                    onChange={(event) => set('company_name', event.target.value)}
                                    className="h-11"
                                    maxLength={160}
                                />
                                <InputError message={errors.company_name} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="company_phone">Телефон</Label>
                                    <Input
                                        id="company_phone"
                                        type="tel"
                                        value={values.company_phone}
                                        onChange={(event) => set('company_phone', event.target.value)}
                                        className="h-11"
                                        maxLength={40}
                                    />
                                    <InputError message={errors.company_phone} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="company_email">Имейл</Label>
                                    <Input
                                        id="company_email"
                                        type="email"
                                        value={values.company_email}
                                        onChange={(event) => set('company_email', event.target.value)}
                                        className="h-11"
                                        maxLength={160}
                                    />
                                    <InputError message={errors.company_email} />
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="company_address">Адрес</Label>
                                <Input
                                    id="company_address"
                                    value={values.company_address}
                                    onChange={(event) => set('company_address', event.target.value)}
                                    className="h-11"
                                    maxLength={255}
                                />
                                <InputError message={errors.company_address} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="company_extra">Допълнителен ред</Label>
                                <Input
                                    id="company_extra"
                                    value={values.company_extra}
                                    onChange={(event) => set('company_extra', event.target.value)}
                                    placeholder="напр. ЕИК, сайт или Instagram"
                                    className="h-11"
                                    maxLength={255}
                                />
                                <InputError message={errors.company_extra} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Оферти по подразбиране</CardTitle>
                            <CardDescription>Важат само за нови оферти. Вече създадените не се променят.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="default_quote_validity_days">Валидност (дни)</Label>
                                <NumericInput
                                    id="default_quote_validity_days"
                                    value={values.default_quote_validity_days}
                                    onValueChange={(value) => set('default_quote_validity_days', value)}
                                    allowDecimals={false}
                                    className="h-11 max-w-32"
                                />
                                <InputError message={errors.default_quote_validity_days} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="default_quote_terms">Условия по подразбиране</Label>
                                <Textarea
                                    id="default_quote_terms"
                                    value={values.default_quote_terms}
                                    onChange={(event) => set('default_quote_terms', event.target.value)}
                                    rows={5}
                                    maxLength={5000}
                                    placeholder="Начин на плащане, капаро, срок за потвърждение…"
                                />
                                <InputError message={errors.default_quote_terms} />
                            </div>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing}>
                        {processing ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Save aria-hidden="true" />}
                        Запази
                    </Button>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
