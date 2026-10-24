import { Head } from '@inertiajs/react';

import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

export default function Appearance() {
    return (
        <AppLayout title="Настройки" description="Външен вид">
            <Head title="Външен вид" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Външен вид" description="Изберете светла, тъмна или системна тема" />
                    <AppearanceTabs />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
