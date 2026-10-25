import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { accountNavItems, isActiveSection, isActiveUrl, readyItems, readySections, sectionUrl } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Долна навигация за телефон — достъпна с една ръка, с големи touch targets.
 * Показва трите основни секции плюс „Още“ за подопциите и профила.
 * Скрива се при печат и на широки екрани (там е страничното меню).
 */
export function MobileNav() {
    const page = usePage();
    const sections = readySections();
    const [open, setOpen] = useState(false);

    // Листът се затваря при навигация, за да не остане върху новата страница.
    useEffect(() => setOpen(false), [page.url]);

    return (
        <nav
            aria-label="Основна навигация"
            className="border-sidebar-border/70 bg-background/95 fixed inset-x-0 bottom-0 z-40 border-t pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden print:hidden"
        >
            <ul className="grid" style={{ gridTemplateColumns: `repeat(${sections.length + 1}, minmax(0, 1fr))` }}>
                {sections.map((section) => {
                    const active = isActiveSection(section, page.url);

                    return (
                        <li key={section.key}>
                            <Link
                                href={sectionUrl(section)}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'focus-visible:ring-ring flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 py-2 text-[11px] font-medium transition-colors focus-visible:ring-2 focus-visible:outline-hidden focus-visible:ring-inset',
                                    active ? 'text-primary' : 'text-muted-foreground',
                                )}
                            >
                                <section.icon className="size-5" aria-hidden="true" />
                                <span className="truncate">{section.shortTitle}</span>
                            </Link>
                        </li>
                    );
                })}

                <li>
                    <Sheet open={open} onOpenChange={setOpen}>
                        <SheetTrigger
                            className={cn(
                                'focus-visible:ring-ring text-muted-foreground flex min-h-14 w-full flex-col items-center justify-center gap-0.5 px-1 py-2 text-[11px] font-medium focus-visible:ring-2 focus-visible:outline-hidden focus-visible:ring-inset',
                            )}
                        >
                            <Menu className="size-5" aria-hidden="true" />
                            <span>Още</span>
                        </SheetTrigger>

                        <SheetContent side="bottom" className="max-h-[85svh] overflow-y-auto">
                            <SheetHeader>
                                <SheetTitle>Навигация</SheetTitle>
                            </SheetHeader>

                            <div className="space-y-5 px-4 pb-6">
                                {sections
                                    .filter((section) => readyItems(section).length > 0)
                                    .map((section) => (
                                        <div key={section.key}>
                                            <p className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">{section.title}</p>
                                            <ul className="grid gap-0.5">
                                                {readyItems(section).map((item) => (
                                                    <li key={item.url}>
                                                        <SheetLink url={item.url} active={isActiveUrl(item.url, page.url)}>
                                                            <item.icon className="size-4" aria-hidden="true" />
                                                            {item.title}
                                                        </SheetLink>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}

                                <div>
                                    <p className="text-muted-foreground mb-1 text-xs font-medium tracking-wide uppercase">Профил</p>
                                    <ul className="grid gap-0.5">
                                        {accountNavItems.map((item) => (
                                            <li key={item.url}>
                                                <SheetLink url={item.url} active={isActiveUrl(item.url, page.url)}>
                                                    <item.icon className="size-4" aria-hidden="true" />
                                                    {item.title}
                                                </SheetLink>
                                            </li>
                                        ))}
                                        <li>
                                            <Link
                                                href={route('logout')}
                                                method="post"
                                                as="button"
                                                className="hover:bg-accent focus-visible:ring-ring flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-sm focus-visible:ring-2 focus-visible:outline-hidden"
                                            >
                                                <LogOut className="size-4" aria-hidden="true" />
                                                Изход
                                            </Link>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </li>
            </ul>
        </nav>
    );
}

function SheetLink({ url, active, children }: { url: string; active: boolean; children: React.ReactNode }) {
    return (
        <Link
            href={url}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'hover:bg-accent focus-visible:ring-ring flex min-h-11 items-center gap-3 rounded-md px-3 text-sm focus-visible:ring-2 focus-visible:outline-hidden',
                active && 'bg-accent text-accent-foreground font-medium',
            )}
        >
            {children}
        </Link>
    );
}
