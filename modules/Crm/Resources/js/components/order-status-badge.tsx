import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const CLASSES: Record<string, string> = {
    pending: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    confirmed: 'border-sky-500/40 bg-sky-500/10 text-sky-600 dark:text-sky-400',
    preparing: 'border-violet-500/40 bg-violet-500/10 text-violet-600 dark:text-violet-400',
    in_progress: 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400',
    completed: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    cancelled: 'border-muted-foreground/30 text-muted-foreground',
};

export function OrderStatusBadge({ status, label, className }: { status: string; label: string; className?: string }) {
    return (
        <Badge variant="outline" className={cn(CLASSES[status] ?? '', className)}>
            {label}
        </Badge>
    );
}
