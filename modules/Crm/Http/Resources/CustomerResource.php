<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Resources;

use App\Http\Resources\Concerns\ExposesDeletionInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Crm\Models\Customer;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    use ExposesDeletionInfo;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'address' => $this->address,
            'source' => $this->source,
            'source_label' => $this->sourceLabel(),
            'notes' => $this->notes,
            'is_archived' => (bool) $this->is_archived,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            ...$this->deletionInfo(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
