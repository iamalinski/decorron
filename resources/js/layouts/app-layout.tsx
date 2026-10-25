import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { FlashToasts } from '@/components/flash-toasts';
import { MobileNav } from '@/components/mobile-nav';
import { SectionNav } from '@/components/section-nav';
import { Toaster } from '@/components/ui/sonner';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    /** Заглавието в горната лента. */
    title?: string;
    description?: string;
    /** Бутони вдясно в горната лента (напр. „Запази“). */
    actions?: ReactNode;
    /** Махни вътрешния padding, когато страницата сама го управлява. */
    bare?: boolean;
}

export default function AppLayout({ children, title, description, actions, bare = false }: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-h-svh">
                <header className="border-sidebar-border/60 bg-background/95 sticky top-0 z-30 flex min-h-16 shrink-0 items-center gap-3 border-b px-4 backdrop-blur md:px-6 print:hidden">
                    <div className="min-w-0 flex-1">
                        {title && <h1 className="truncate text-lg font-semibold tracking-tight md:text-xl">{title}</h1>}
                        {description && <p className="text-muted-foreground truncate text-sm">{description}</p>}
                    </div>
                    {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
                </header>

                <SectionNav />

                <main className={cn('flex-1 pb-20 md:pb-8', bare ? '' : 'px-4 py-4 md:px-6 md:py-6')}>{children}</main>

                <MobileNav />
            </AppContent>

            <Toaster />
            <FlashToasts />
        </AppShell>
    );
}
