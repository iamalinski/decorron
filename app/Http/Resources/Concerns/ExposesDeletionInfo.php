<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

/**
 * Данните за Кошчето към един запис.
 *
 * Проверките изискват по няколко преброявания, затова се включват само
 * там, където има бутон за изтриване върху конкретния запис (екраните с
 * подробности), а не за всеки ред в дългите списъци.
 */
trait ExposesDeletionInfo
{
    private bool $includeDeletionInfo = false;

    public function withDeletionInfo(): static
    {
        $this->includeDeletionInfo = true;

        return $this;
    }

    /** @return array<string, mixed> */
    protected function deletionInfo(): array
    {
        return [
            'can_be_deleted' => $this->when(
                $this->includeDeletionInfo,
                fn (): bool => $this->resource->canBeDeleted(),
            ),
            'deletion_blockers' => $this->when(
                $this->includeDeletionInfo,
                fn (): array => $this->resource->deletionBlockers(),
            ),
            'can_be_deleted_forever' => $this->when(
                $this->includeDeletionInfo,
                fn (): bool => $this->resource->canBeDeletedForever(),
            ),
            'restore_blockers' => $this->when(
                $this->includeDeletionInfo,
                fn (): array => $this->resource->restoreBlockers(),
            ),
        ];
    }
}
