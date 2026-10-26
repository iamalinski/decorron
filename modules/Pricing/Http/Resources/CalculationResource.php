<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Resources;

use App\Http\Resources\Concerns\ExposesDeletionInfo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Models\Calculation;

/**
 * @mixin Calculation
 */
class CalculationResource extends JsonResource
{
    use ExposesDeletionInfo;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'calculation_number' => $this->calculation_number,
            'service_name' => $this->service_name,
            'client_name' => $this->client_name,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn (): ?string => $this->customer?->name),
            'crm_opportunity_id' => $this->crm_opportunity_id,
            'opportunity_title' => $this->whenLoaded('opportunity', fn (): ?string => $this->opportunity?->title),
            'notes' => $this->notes,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            ...$this->deletionInfo(),
            'calculated_at' => $this->calculated_at?->toDateString(),
            'material_cost_total' => (string) $this->material_cost_total,
            'labor_cost' => (string) $this->labor_cost,
            'other_costs' => (string) $this->other_costs,
            'total_cost' => (string) $this->total_cost,
            'target_net_margin' => (string) $this->target_net_margin,
            'corporate_tax_rate_snapshot' => (string) $this->corporate_tax_rate_snapshot,
            'dividend_tax_rate_snapshot' => (string) $this->dividend_tax_rate_snapshot,
            'vat_rate_snapshot' => (string) $this->vat_rate_snapshot,
            'profit_before_tax' => (string) $this->profit_before_tax,
            'corporate_tax_amount' => (string) $this->corporate_tax_amount,
            'profit_after_corporate_tax' => (string) $this->profit_after_corporate_tax,
            'dividend_tax_amount' => (string) $this->dividend_tax_amount,
            'net_profit' => (string) $this->net_profit,
            'final_price' => (string) $this->final_price,
            'actual_net_margin' => (string) $this->actual_net_margin,
            'price_was_rounded' => (bool) $this->price_was_rounded,
            'taxes_applied' => (bool) $this->taxes_applied,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => CalculationItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
