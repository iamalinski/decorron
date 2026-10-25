<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Общите правила за Кошчето, споделени от всички модули.
 *
 * Контролерите не повтарят проверките — всеки от трите метода връща
 * готово съобщение за грешка или null, ако действието е изпълнено.
 */
trait TrashesRecords
{
    /**
     * Премества записа в Кошчето, ако нищо активно не сочи към него.
     *
     * @return string|null текст за showErrors, или null при успех
     */
    protected function moveToTrash(Model $record): ?string
    {
        $wording = $record::trashWording();

        if (! $record->canBeDeleted()) {
            return $wording['subject'].' не може да бъде '.$wording['deleted']
                .', защото към '.$wording['pronoun'].' все още сочат активни записи ('
                .$record->blockerSummary().'). Изтрийте първо тях.';
        }

        $record->delete();

        return null;
    }

    /**
     * Връща записа от Кошчето, ако „над“ него няма изтрит запис.
     */
    protected function restoreFromTrash(Model $record): ?string
    {
        $wording = $record::trashWording();
        $blockers = $record->restoreBlockers();

        if ($blockers !== []) {
            return $wording['subject'].' не може да бъде '.$wording['restored']
                .'. Първо върнете от Кошчето: '.implode(' и ', $blockers).'.';
        }

        $record->restore();

        return null;
    }

    /**
     * Изтрива записа окончателно.
     *
     * Тук пречат и записите в Кошчето: докато те съществуват в базата,
     * връзките към тях не могат да бъдат премахнати без загуба на данни.
     */
    protected function deleteForever(Model $record): ?string
    {
        $wording = $record::trashWording();

        if (! $record->canBeDeletedForever()) {
            return $wording['subject'].' не може да бъде '.$wording['deleted']
                .' окончателно, защото в базата още има свързани записи ('
                .$record->blockerSummary(true).'). Изтрийте ги окончателно от Кошчето.';
        }

        $record->forceDelete();

        return null;
    }
}
