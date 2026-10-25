<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\QuoteVersion;

/**
 * @mixin QuoteVersion
 */
class QuoteVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => (int) $this->version,
            'total' => (string) $this->total,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'payload' => $this->payload,
        ];
    }
}
