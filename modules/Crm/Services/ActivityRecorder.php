<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Models\User;
use Modules\Crm\Models\ActivityLog;
use Modules\Crm\Models\Opportunity;

/**
 * Единствената точка, през която се пишат записи в хронологията.
 *
 * Така всички събития имат еднакъв формат и винаги е ясно дали записът е
 * автоматичен (системата има данни за него) или ръчен (потребителят го твърди).
 */
final class ActivityRecorder
{
    /** @param  array<string, mixed>  $meta */
    public function log(
        User $user,
        string $type,
        string $description,
        ?Opportunity $opportunity = null,
        ?int $customerId = null,
        bool $automatic = true,
        array $meta = [],
    ): ActivityLog {
        $log = new ActivityLog([
            'customer_id' => $customerId ?? $opportunity?->customer_id,
            'opportunity_id' => $opportunity?->getKey(),
            'type' => $type,
            'description' => mb_substr($description, 0, 500),
            'is_automatic' => $automatic,
            'meta' => $meta === [] ? null : $meta,
        ]);

        $log->user_id = $user->getKey();
        $log->save();

        return $log;
    }
}
