<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Resources;

use App\Http\Resources\Concerns\ExposesDeletionInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\Order;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    use ExposesDeletionInfo;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),

            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name_snapshot,
            'customer_phone' => $this->customer_phone_snapshot,
            'customer_email' => $this->customer_email_snapshot,

            'crm_opportunity_id' => $this->crm_opportunity_id,
            'opportunity_title' => $this->whenLoaded('opportunity', fn (): ?string => $this->opportunity?->title),
            'quote_id' => $this->quote_id,
            'quote_number' => $this->whenLoaded('quote', fn (): ?string => $this->quote?->quote_number),
            'quote_version' => $this->quote_version === null ? null : (int) $this->quote_version,

            'event_date' => $this->event_date?->toDateString(),
            'event_location' => $this->event_location,

            'agreed_total' => (string) $this->agreed_total,
            'deposit_amount' => $this->deposit_amount === null ? null : (string) $this->deposit_amount,
            'currency' => $this->currency,

            'execution_notes' => $this->execution_notes,
            'deadline_note' => $this->deadline_note,

            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            ...$this->deletionInfo(),
            'created_at' => $this->created_at?->toIso8601String(),

            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
