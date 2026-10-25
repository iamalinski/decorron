import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/** Цветови нюанс за всеки етап — за да се чете дъската с един поглед. */
const STAGE_CLASSES: Record<string, string> = {
    new: 'border-sky-500/40 bg-sky-500/10 text-sky-600 dark:text-sky-400',
    consultation: 'border-violet-500/40 bg-violet-500/10 text-violet-600 dark:text-violet-400',
    pricing: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
    quote_sent: 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400',
    negotiation: 'border-orange-500/40 bg-orange-500/10 text-orange-600 dark:text-orange-400',
};

const STATUS_CLASSES: Record<string, string> = {
    won: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    lost: 'border-rose-500/40 bg-rose-500/10 text-rose-600 dark:text-rose-400',
};

export function StageBadge({ stage, label, className }: { stage: string; label: string; className?: string }) {
    return (
        <Badge variant="outline" className={cn(STAGE_CLASSES[stage] ?? '', className)}>
            {label}
        </Badge>
    );
}

export function StatusBadge({ status, label, className }: { status: string; label: string; className?: string }) {
    if (status === 'open') {
        return null;
    }

    return (
        <Badge variant="outline" className={cn(STATUS_CLASSES[status] ?? '', className)}>
            {label}
        </Badge>
    );
}
