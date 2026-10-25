import { activeSection, isActiveUrl, readyItems } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';

/**
 * Хоризонтална лента с подопциите на текущата секция.
 *
 * Показва се само на телефон — на широк екран същите опции са в страничното
 * меню и повтарянето им само би отнело място.
 */
export function SectionNav() {
    const page = usePage();
    const section = activeSection(page.url);
    const items = section ? readyItems(section) : [];

    if (items.length < 2) {
        return null;
    }

    return (
        <nav
            aria-label={`Подменю: ${section!.title}`}
            className="border-sidebar-border/60 bg-background/95 sticky top-16 z-20 border-b backdrop-blur md:hidden print:hidden"
        >
            <ul className="flex gap-1 overflow-x-auto px-3 py-2">
                {items.map((item) => {
                    const active = isActiveUrl(item.url, page.url);

                    return (
                        <li key={item.url}>
                            <Link
                                href={item.url}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'focus-visible:ring-ring flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-xs font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:outline-hidden',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-accent border-transparent',
                                )}
                            >
                                <item.icon className="size-3.5" aria-hidden="true" />
                                {item.shortTitle ?? item.title}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
