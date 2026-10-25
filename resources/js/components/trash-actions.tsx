import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import type { DeletionBlocker } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ArchiveRestore, ArrowRight, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type Mode = 'delete' | 'blocked' | 'restore' | 'force';

export interface TrashActionsProps {
    /** Кога записът е в Кошчето. null/undefined означава активен запис. */
    deletedAt?: string | null;
    /**
     * Какво пречи на изтриването. Известно е само на екраните с подробности;
     * в списъците остава undefined и проверката я прави сървърът.
     */
    blockers?: DeletionBlocker[];
    /** Връзка към списъка със записите от даден вид, ако има такъв екран. */
    blockerUrl?: (key: string) => string | null;
    /** Как се казва записът в изречение: „Мария Иванова“, „Оферта OF-2026-0001“. */
    name: string;
    /** В какво се изтрива: „клиента“, „запитването“, „офертата“… */
    subject: string;
    destroyUrl: string;
    restoreUrl: string;
    forceUrl: string;
    /** Какво още ще изчезне заедно със записа — показва се в потвърждението. */
    alsoRemoved?: string;
    /** Компактен вид за редовете в списък. */
    compact?: boolean;
}

/**
 * Бутоните за Кошчето към един запис.
 *
 * Бутонът „Изтрий“ се показва винаги. Когато към записа още сочат активни
 * записи, диалогът обяснява какво пречи, вместо действието да изчезне без
 * обяснение. Сървърът прави същата проверка — тук тя е само за да се види
 * причината преди натискането.
 */
export function TrashActions({
    deletedAt,
    blockers,
    blockerUrl,
    name,
    subject,
    destroyUrl,
    restoreUrl,
    forceUrl,
    alsoRemoved,
    compact = false,
}: TrashActionsProps) {
    const [mode, setMode] = useState<Mode | null>(null);
    const isTrashed = Boolean(deletedAt);
    const blocked = (blockers?.length ?? 0) > 0;

    const fail = (errors: Record<string, string>) => {
        toast.error(errors.delete ?? 'Действието не беше изпълнено.');
    };

    const run = (method: 'delete' | 'patch', url: string) => {
        setMode(null);

        if (method === 'delete') {
            router.delete(url, { preserveScroll: true, onError: fail });

            return;
        }

        router.patch(url, {}, { preserveScroll: true, onError: fail });
    };

    const size = compact ? 'icon' : 'sm';

    if (isTrashed) {
        return (
            <>
                <Button size={size} variant="outline" onClick={() => setMode('restore')} title={compact ? 'Възстанови' : undefined}>
                    <ArchiveRestore aria-hidden="true" />
                    {compact ? <span className="sr-only">Възстанови</span> : <span className="hidden sm:inline">Възстанови</span>}
                </Button>
                <Button size={size} variant="outline" onClick={() => setMode('force')} title={compact ? 'Изтрий окончателно' : undefined}>
                    <Trash2 aria-hidden="true" />
                    {compact ? <span className="sr-only">Изтрий окончателно</span> : <span className="hidden sm:inline">Изтрий окончателно</span>}
                </Button>

                <AlertDialog open={mode === 'restore'} onOpenChange={(open) => !open && setMode(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Връщане от Кошчето?</AlertDialogTitle>
                            <AlertDialogDescription>{name} ще се появи отново в списъците, таблото и календара.</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Отказ</AlertDialogCancel>
                            <AlertDialogAction onClick={() => run('patch', restoreUrl)}>Възстанови</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <AlertDialog open={mode === 'force'} onOpenChange={(open) => !open && setMode(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Окончателно изтриване?</AlertDialogTitle>
                            <AlertDialogDescription>
                                {name} ще изчезне завинаги от базата данни. {alsoRemoved} Връщане след това няма да е възможно.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Отказ</AlertDialogCancel>
                            <AlertDialogAction onClick={() => run('delete', forceUrl)}>Изтрий окончателно</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </>
        );
    }

    return (
        <>
            <Button size={size} variant="outline" onClick={() => setMode(blocked ? 'blocked' : 'delete')} title={compact ? 'Изтрий' : undefined}>
                <Trash2 aria-hidden="true" />
                {compact ? <span className="sr-only">Изтрий</span> : <span className="hidden sm:inline">Изтрий</span>}
            </Button>

            <AlertDialog open={mode === 'blocked'} onOpenChange={(open) => !open && setMode(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Изтриването не е възможно</AlertDialogTitle>
                        <AlertDialogDescription>Към {subject} все още сочат записи, които трябва да бъдат изтрити първи:</AlertDialogDescription>
                    </AlertDialogHeader>
                    <ul className="text-foreground grid gap-1 text-sm">
                        {blockers?.map((blocker) => {
                            const href = blockerUrl?.(blocker.key);
                            const text = `${blocker.count} ${blocker.label}`;

                            return (
                                <li key={blocker.key} className="bg-muted/50 rounded-md">
                                    {href ? (
                                        <Link href={href} className="hover:bg-accent flex items-center justify-between rounded-md px-3 py-2">
                                            {text}
                                            <ArrowRight className="size-4" aria-hidden="true" />
                                        </Link>
                                    ) : (
                                        <span className="block px-3 py-2">{text}</span>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    <p className="text-muted-foreground text-xs">
                        Отворете ги от списъците на тази страница или от съответния модул, изтрийте ги и се върнете тук. Записите в Кошчето не се
                        броят — щом изчистите веригата, изтриването става възможно.
                    </p>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Разбрах</AlertDialogCancel>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={mode === 'delete'} onOpenChange={(open) => !open && setMode(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Преместване в Кошчето?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {name} ще изчезне от списъците, таблото и календара. {alsoRemoved} Записът остава в Кошчето и може да бъде върнат или
                            изтрит окончателно.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Отказ</AlertDialogCancel>
                        <AlertDialogAction onClick={() => run('delete', destroyUrl)}>Изтрий</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

/** Лентата в горната част на екран, чийто запис е в Кошчето. */
export function TrashBanner({ children }: { children: React.ReactNode }) {
    return (
        <div className="border-destructive/40 bg-destructive/5 text-destructive flex items-start gap-2 rounded-lg border p-3 text-sm">
            <Trash2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>{children}</p>
        </div>
    );
}
