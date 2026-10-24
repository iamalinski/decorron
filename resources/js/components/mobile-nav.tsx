import { isActiveUrl, mobileNavItems } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';

/**
 * Долна навигация за телефон — достъпна с една ръка, с големи touch targets.
 * Скрива се при печат и на широки екрани (там е страничното меню).
 */
export function MobileNav() {
    const page = usePage();

    return (
        <nav
            aria-label="Основна навигация"
            className="border-sidebar-border/70 bg-background/95 fixed inset-x-0 bottom-0 z-40 border-t pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden print:hidden"
        >
            <ul className="grid grid-cols-5">
                {mobileNavItems.map((item) => {
                    const active = isActiveUrl(item.url, page.url);

                    return (
                        <li key={item.url}>
                            <Link
                                href={item.url}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'focus-visible:ring-ring flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 py-2 text-[11px] font-medium transition-colors focus-visible:ring-2 focus-visible:outline-hidden focus-visible:ring-inset',
                                    active ? 'text-primary' : 'text-muted-foreground',
                                )}
                            >
                                {item.icon && <item.icon className="size-5" aria-hidden="true" />}
                                <span className="truncate">{item.title}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
