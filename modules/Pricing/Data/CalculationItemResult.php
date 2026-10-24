<?php

declare(strict_types=1);

namespace Modules\Pricing\Data;

final readonly class CalculationItemResult
{
    public function __construct(
        public string $quantity,
        public string $inputMode,
        public ?string $enteredTotalCost,
        public string $unitPrice,
        public string $lineTotal,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'quantity' => $this->quantity,
            'input_mode' => $this->inputMode,
            'entered_total_cost' => $this->enteredTotalCost,
            'unit_price' => $this->unitPrice,
            'line_total' => $this->lineTotal,
        ];
    }
}
