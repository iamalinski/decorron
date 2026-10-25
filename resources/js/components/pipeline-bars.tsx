import { Link } from '@inertiajs/react';

export interface PipelineStage {
    stage: string;
    label: string;
    count: number;
}

/**
 * Хоризонтална диаграма на отворените запитвания по етапи.
 *
 * Изчертава се с CSS от реалните броеве — без библиотека и без изгладени
 * или измислени стойности.
 */
export function PipelineBars({ stages, hrefFor }: { stages: PipelineStage[]; hrefFor?: (stage: string) => string }) {
    const max = Math.max(1, ...stages.map((stage) => stage.count));
    const total = stages.reduce((sum, stage) => sum + stage.count, 0);

    if (total === 0) {
        return <p className="text-muted-foreground text-sm">Няма отворени запитвания.</p>;
    }

    return (
        <ul className="grid gap-2">
            {stages.map((stage) => {
                const width = `${Math.round((stage.count / max) * 100)}%`;

                const row = (
                    <>
                        <span className="w-36 shrink-0 truncate text-xs">{stage.label}</span>
                        <span className="bg-muted h-5 flex-1 overflow-hidden rounded">
                            <span className="bg-primary/70 block h-full rounded" style={{ width }} />
                        </span>
                        <span className="w-6 shrink-0 text-right text-xs tabular-nums">{stage.count}</span>
                    </>
                );

                return (
                    <li key={stage.stage}>
                        {hrefFor ? (
                            <Link
                                href={hrefFor(stage.stage)}
                                className="hover:bg-accent/40 focus-visible:ring-ring flex items-center gap-2 rounded px-1 py-0.5 focus-visible:ring-2 focus-visible:outline-hidden"
                                aria-label={`${stage.label}: ${stage.count}`}
                            >
                                {row}
                            </Link>
                        ) : (
                            <div className="flex items-center gap-2 px-1 py-0.5">{row}</div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
