<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\ActivityLog;

/**
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'is_automatic' => (bool) $this->is_automatic,
            'meta' => $this->meta,
            'opportunity_id' => $this->opportunity_id,
            'customer_id' => $this->customer_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
