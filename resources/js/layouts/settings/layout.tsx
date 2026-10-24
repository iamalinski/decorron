import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

const sidebarNavItems: NavItem[] = [
    { title: 'Ценообразуване', url: '/settings/pricing', icon: null },
    { title: 'Профил', url: '/settings/profile', icon: null },
    { title: 'Парола', url: '/settings/password', icon: null },
    { title: 'Външен вид', url: '/settings/appearance', icon: null },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = usePage().url.split('?')[0];

    return (
        <div className="mx-auto max-w-4xl">
            <nav aria-label="Настройки" className="mb-6 flex gap-1 overflow-x-auto pb-1">
                {sidebarNavItems.map((item) => (
                    <Button key={item.url} size="sm" variant="ghost" asChild className={cn('shrink-0', currentPath === item.url && 'bg-muted')}>
                        <Link href={item.url} prefetch aria-current={currentPath === item.url ? 'page' : undefined}>
                            {item.title}
                        </Link>
                    </Button>
                ))}
            </nav>

            <section className="max-w-xl space-y-10">{children}</section>
        </div>
    );
}
