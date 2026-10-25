<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Resources;

use App\Http\Resources\Concerns\ExposesDeletionInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\Task;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    use ExposesDeletionInfo;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'due_on' => $this->due_on?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn (): ?string => $this->customer?->name),
            'crm_opportunity_id' => $this->crm_opportunity_id,
            'opportunity_title' => $this->whenLoaded('opportunity', fn (): ?string => $this->opportunity?->title),
            'quote_id' => $this->quote_id,
            'order_id' => $this->order_id,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            ...$this->deletionInfo(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
