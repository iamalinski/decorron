import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo } from 'react';
import type { CalendarEntry } from '../types';

interface CalendarPageProps {
    month: string;
    previousMonth: string;
    nextMonth: string;
    today: string;
    entries: CalendarEntry[];
}

const KIND_LABELS: Record<CalendarEntry['kind'], string> = {
    order: 'Поръчка',
    opportunity: 'Запитване',
    quote: 'Оферта',
    task: 'Задача',
};

const KIND_CLASSES: Record<CalendarEntry['kind'], string> = {
    order: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    opportunity: 'border-sky-500/40 bg-sky-500/10 text-sky-600 dark:text-sky-400',
    quote: 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400',
    task: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
};

/** Седмицата започва в понеделник — както е прието в България. */
const WEEKDAYS = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'нд'];

export default function Calendar({ month, previousMonth, nextMonth, today, entries }: CalendarPageProps) {
    const byDate = useMemo(() => {
        const map = new Map<string, CalendarEntry[]>();

        for (const entry of entries) {
            map.set(entry.date, [...(map.get(entry.date) ?? []), entry]);
        }

        return map;
    }, [entries]);

    const days = useMemo(() => buildMonthGrid(month), [month]);

    const monthLabel = new Intl.DateTimeFormat('bg-BG', { month: 'long', year: 'numeric' }).format(new Date(`${month}-01T00:00:00`));

    return (
        <AppLayout
            title="Календар"
            description={monthLabel}
            actions={
                <div className="flex gap-1">
                    <Button asChild size="icon" variant="outline" className="size-9">
                        <Link href={route('crm.calendar', { month: previousMonth })} aria-label="Предишен месец">
                            <ChevronLeft />
                        </Link>
                    </Button>
                    <Button asChild size="icon" variant="outline" className="size-9">
                        <Link href={route('crm.calendar', { month: nextMonth })} aria-label="Следващ месец">
                            <ChevronRight />
                        </Link>
                    </Button>
                </div>
            }
        >
            <Head title={`Календар · ${monthLabel}`} />

            <div className="mx-auto max-w-5xl space-y-4">
                {/* Мрежа на широк екран */}
                <Card className="hidden md:block">
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-7 gap-px text-center text-xs font-medium">
                            {WEEKDAYS.map((day) => (
                                <div key={day} className="text-muted-foreground pb-2">
                                    {day}
                                </div>
                            ))}
                        </div>

                        <div className="grid grid-cols-7 gap-px">
                            {days.map((day) => {
                                const dayEntries = day.date === null ? [] : (byDate.get(day.date) ?? []);

                                return (
                                    <div
                                        key={day.key}
                                        className={cn(
                                            'min-h-24 border p-1.5 text-left',
                                            day.date === null && 'bg-muted/30',
                                            day.date === today && 'border-primary',
                                        )}
                                    >
                                        {day.date !== null && (
                                            <>
                                                <p
                                                    className={cn(
                                                        'text-xs tabular-nums',
                                                        day.date === today ? 'text-primary font-bold' : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {day.number}
                                                </p>
                                                <ul className="mt-1 space-y-0.5">
                                                    {dayEntries.map((entry, index) => (
                                                        <li key={`${entry.kind}-${index}`}>
                                                            <Link
                                                                href={entry.url}
                                                                className={cn(
                                                                    'block truncate rounded border px-1 py-0.5 text-[11px] hover:underline',
                                                                    KIND_CLASSES[entry.kind],
                                                                )}
                                                                title={`${KIND_LABELS[entry.kind]}: ${entry.title}`}
                                                            >
                                                                {entry.title}
                                                            </Link>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Списък по дни — основният изглед на телефон */}
                <div className="md:hidden">
                    {entries.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title="Няма записи през този месец"
                            description="Тук се показват датите на поръчките и запитванията, сроковете на офертите и задачите."
                        />
                    ) : (
                        <ul className="grid gap-3">
                            {[...byDate.entries()]
                                .sort(([a], [b]) => a.localeCompare(b))
                                .map(([date, dayEntries]) => (
                                    <li key={date}>
                                        <p className={cn('mb-1.5 text-sm font-medium', date === today && 'text-primary')}>
                                            {formatDayLabel(date)}
                                            {date === today && ' · днес'}
                                        </p>
                                        <ul className="grid gap-2">
                                            {dayEntries.map((entry, index) => (
                                                <li key={`${entry.kind}-${index}`}>
                                                    <Link
                                                        href={entry.url}
                                                        className="bg-card hover:bg-accent/40 focus-visible:ring-ring block rounded-lg border p-3 transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                                                    >
                                                        <div className="flex items-start justify-between gap-2">
                                                            <p className="min-w-0 truncate font-medium">{entry.title}</p>
                                                            <Badge variant="outline" className={cn('shrink-0', KIND_CLASSES[entry.kind])}>
                                                                {KIND_LABELS[entry.kind]}
                                                            </Badge>
                                                        </div>
                                                        {entry.subtitle && (
                                                            <p className="text-muted-foreground mt-0.5 truncate text-sm">
                                                                {entry.subtitle}
                                                            </p>
                                                        )}
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </li>
                                ))}
                        </ul>
                    )}
                </div>

                {entries.length === 0 && (
                    <p className="text-muted-foreground hidden text-center text-sm md:block">
                        Няма записи през този месец.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}

interface GridDay {
    key: string;
    date: string | null;
    number: number | null;
}

/** Клетките на месеца, подравнени към седмица, започваща в понеделник. */
function buildMonthGrid(month: string): GridDay[] {
    const first = new Date(`${month}-01T00:00:00`);
    const year = first.getFullYear();
    const monthIndex = first.getMonth();

    // getDay(): 0 = неделя. Преобразуваме към 0 = понеделник.
    const leading = (first.getDay() + 6) % 7;
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

    const cells: GridDay[] = [];

    for (let index = 0; index < leading; index += 1) {
        cells.push({ key: `lead-${index}`, date: null, number: null });
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const date = `${month}-${String(day).padStart(2, '0')}`;
        cells.push({ key: date, date, number: day });
    }

    while (cells.length % 7 !== 0) {
        cells.push({ key: `trail-${cells.length}`, date: null, number: null });
    }

    return cells;
}

function formatDayLabel(date: string): string {
    return new Intl.DateTimeFormat('bg-BG', { weekday: 'short', day: 'numeric', month: 'long' }).format(
        new Date(`${date}T00:00:00`),
    );
}
