import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

interface StatCardProps {
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    href?: string;
    /** Подчертава показател, който изисква внимание. */
    tone?: 'default' | 'warning';
}

/** Един показател на таблото. Показва само реално преброени стойности. */
export function StatCard({ label, value, hint, icon: Icon, href, tone = 'default' }: StatCardProps) {
    const body = (
        <CardContent className="pt-5 pb-5">
            <div className="flex items-start justify-between gap-2">
                <p className="text-muted-foreground text-xs tracking-wide uppercase">{label}</p>
                {Icon && (
                    <Icon className={cn('size-4 shrink-0', tone === 'warning' ? 'text-destructive' : 'text-muted-foreground')} aria-hidden="true" />
                )}
            </div>
            <p className={cn('mt-1 text-2xl font-bold tabular-nums', tone === 'warning' && Number(value) > 0 && 'text-destructive')}>{value}</p>
            {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
        </CardContent>
    );

    if (href) {
        return (
            <Card className="hover:bg-accent/30 transition-colors">
                <Link href={href} className="focus-visible:ring-ring block rounded-xl focus-visible:ring-2 focus-visible:outline-hidden">
                    {body}
                </Link>
            </Card>
        );
    }

    return <Card>{body}</Card>;
}
