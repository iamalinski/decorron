<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Двустепенно изтриване за записите, които участват във веригата
 * клиент → запитване → оферта → поръчка.
 *
 * Първото изтриване премества записа в Кошчето: той изчезва от всички
 * списъци, справки и календара, но може да бъде възстановен. Второто
 * (от Кошчето) го изтрива окончателно.
 *
 * Изтрива се само „отдолу нагоре“: запис, към който още сочат активни
 * записи, не се изтрива. Записите в Кошчето не пречат на мекото изтриване,
 * но пречат на окончателното — иначе базата би останала с висящи връзки.
 */
trait Trashable
{
    use SoftDeletes;

    /**
     * Какво сочи към записа и пречи на изтриването му.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    abstract public function deletionBlockers(bool $includeTrashed = false): array;

    /**
     * Българската граматика за съобщенията — на едно място, за да не се
     * получат изречения като „Офертата е изтрит“.
     *
     * @return array{subject: string, deleted: string, restored: string, pronoun: string}
     */
    abstract public static function trashWording(): array;

    /** Може ли да отиде в Кошчето. */
    public function canBeDeleted(): bool
    {
        return $this->deletionBlockers() === [];
    }

    /** Може ли да бъде изтрит окончателно от базата. */
    public function canBeDeletedForever(): bool
    {
        return $this->deletionBlockers(true) === [];
    }

    /**
     * Кои записи „над“ този са в Кошчето и трябва да бъдат върнати първи.
     *
     * Без тази проверка възстановен запис би сочил към несъществуващ
     * клиент или запитване и екранът му би изглеждал счупен.
     *
     * @return array<int, string>
     */
    public function restoreBlockers(): array
    {
        return [];
    }

    public function canBeRestored(): bool
    {
        return $this->restoreBlockers() === [];
    }

    /** Връща етикета, ако родителят съществува, но е в Кошчето. */
    protected function trashedParent(BelongsTo $relation, string $label): ?string
    {
        $parent = $relation->withTrashed()->first();

        return $parent !== null && $parent->trashed() ? $label : null;
    }

    /** „2 оферти, 1 поръчка“ — за съобщения към потребителя. */
    public function blockerSummary(bool $includeTrashed = false): string
    {
        return implode(', ', array_map(
            static fn (array $blocker): string => $blocker['count'].' '.$blocker['label'],
            $this->deletionBlockers($includeTrashed),
        ));
    }

    /**
     * Един ред от списъка с пречки, с правилно множествено число.
     *
     * @return array{key: string, label: string, count: int}|null
     */
    protected static function blocker(string $key, int $count, string $one, string $many): ?array
    {
        return $count === 0 ? null : [
            'key' => $key,
            'label' => $count === 1 ? $one : $many,
            'count' => $count,
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string, count: int}|null>  $blockers
     * @return array<int, array{key: string, label: string, count: int}>
     */
    protected static function onlyBlocking(array $blockers): array
    {
        return array_values(array_filter($blockers));
    }
}
