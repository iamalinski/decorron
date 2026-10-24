<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Models\CalculationItem;

/**
 * @mixin CalculationItem
 */
class CalculationItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'unit_id' => $this->unit_id,
            'material_name' => $this->material_name_snapshot,
            'sku' => $this->sku_snapshot,
            'unit_name' => $this->unit_name_snapshot,
            'unit_symbol' => $this->unit_symbol_snapshot,
            'quantity' => (string) $this->quantity,
            'input_mode' => $this->input_mode,
            'entered_total_cost' => $this->entered_total_cost !== null ? (string) $this->entered_total_cost : null,
            'unit_price' => (string) $this->unit_price,
            'line_total' => (string) $this->line_total,
            'position' => $this->position,
        ];
    }
}
