<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Resources;

use App\Http\Resources\Concerns\ExposesDeletionInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Support\CrmOptions;

/**
 * @mixin Opportunity
 */
class OpportunityResource extends JsonResource
{
    use ExposesDeletionInfo;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn (): string => $this->customer->name),
            'customer_phone' => $this->whenLoaded('customer', fn (): ?string => $this->customer->phone),
            'title' => $this->title,
            'event_type' => $this->event_type,
            'event_date' => $this->event_date?->toDateString(),
            'event_location' => $this->event_location,
            'expected_budget' => $this->expected_budget === null ? null : (string) $this->expected_budget,
            'source' => $this->source,
            'source_label' => $this->source === null ? null : (CrmOptions::SOURCES[$this->source] ?? $this->source),
            'stage' => $this->stage,
            'stage_label' => $this->stageLabel(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'lost_reason' => $this->lost_reason,
            'lost_reason_label' => $this->lostReasonLabel(),
            'lost_reason_note' => $this->lost_reason_note,
            'next_action' => $this->next_action,
            'next_action_due' => $this->next_action_due?->toDateString(),
            'notes' => $this->notes,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            ...$this->deletionInfo(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'activities' => ActivityLogResource::collection($this->whenLoaded('activities')),
        ];
    }
}
