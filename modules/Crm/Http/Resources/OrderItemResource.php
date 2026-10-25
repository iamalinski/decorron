<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\OrderItem;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'details' => $this->details,
            'quantity' => (string) $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'line_total' => (string) $this->line_total,
            'position' => (int) $this->position,
        ];
    }
}
