import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const CLASSES: Record<string, string> = {
    draft: 'border-muted-foreground/30 text-muted-foreground',
    ready: 'border-sky-500/40 bg-sky-500/10 text-sky-600 dark:text-sky-400',
    sent: 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400',
    accepted: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    declined: 'border-rose-500/40 bg-rose-500/10 text-rose-600 dark:text-rose-400',
    expired: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
};

export function QuoteStatusBadge({ status, label, className }: { status: string; label: string; className?: string }) {
    return (
        <Badge variant="outline" className={cn(CLASSES[status] ?? '', className)}>
            {label}
        </Badge>
    );
}
