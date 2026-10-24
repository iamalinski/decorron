<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Осигурява изолация на данните между потребителите.
 *
 * Всички заявки в контролерите минават през ownedBy(), а политиките
 * извършват втора проверка върху вече заредените модели.
 */
trait OwnedByUser
{
    public static function bootOwnedByUser(): void
    {
        static::creating(function (self $model): void {
            if ($model->user_id === null && Auth::hasUser()) {
                $model->user_id = Auth::id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('user_id'),
            $user instanceof User ? $user->getKey() : $user,
        );
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->getKey();
    }
}
