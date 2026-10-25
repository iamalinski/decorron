<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Resources;

use App\Http\Resources\Concerns\ExposesDeletionInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\Quote;

/**
 * @mixin Quote
 *
 * ВАЖНО: тук няма вътрешна себестойност, марж или печалба. Ресурсът се ползва
 * и за клиентския изглед на офертата.
 */
class QuoteResource extends JsonResource
{
    use ExposesDeletionInfo;

    /**
     * Вътрешните бележки се включват само при изричен избор. Клиентският
     * изглед (печат / PDF) никога не ги получава.
     */
    private bool $includeInternal = false;

    public function withInternalNotes(): static
    {
        $this->includeInternal = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_number' => $this->quote_number,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'version' => (int) $this->version,
            'is_editable' => $this->isEditable(),
            'was_sent' => $this->wasSent(),

            'customer_id' => $this->customer_id,
            'crm_opportunity_id' => $this->crm_opportunity_id,
            'opportunity_title' => $this->whenLoaded('opportunity', fn (): ?string => $this->opportunity?->title),

            'issued_at' => $this->issued_at?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'event_date' => $this->event_date?->toDateString(),
            'event_location' => $this->event_location,

            'customer_name' => $this->customer_name_snapshot,
            'customer_contact' => $this->customer_contact_snapshot,
            'customer_phone' => $this->customer_phone_snapshot,
            'customer_email' => $this->customer_email_snapshot,
            'customer_address' => $this->customer_address_snapshot,

            'subtotal' => (string) $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value === null ? null : (string) $this->discount_value,
            'discount_amount' => (string) $this->discount_amount,
            'total' => (string) $this->total,
            'deposit_amount' => $this->deposit_amount === null ? null : (string) $this->deposit_amount,
            'vat_rate' => (string) $this->vat_rate_snapshot,
            'currency' => $this->currency,

            'terms' => $this->terms,
            'internal_notes' => $this->when($this->includeInternal, fn (): ?string => $this->internal_notes),

            'sent_at' => $this->sent_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'declined_at' => $this->declined_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            ...$this->deletionInfo(),
            'created_at' => $this->created_at?->toIso8601String(),

            'items' => QuoteItemResource::collection($this->whenLoaded('items')),
            'versions' => QuoteVersionResource::collection($this->whenLoaded('versions')),
        ];
    }
}
