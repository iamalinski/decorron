<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Unit;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'materials_count' => $this->whenCounted('materials'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
