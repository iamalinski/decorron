<?php

declare(strict_types=1);

namespace Modules\Pricing\Data;

final readonly class CalculationItemInput
{
    public const MODE_TOTAL = 'total';

    public const MODE_UNIT = 'unit';

    public function __construct(
        public string $quantity,
        public string $inputMode,
        public ?string $enteredTotalCost = null,
        public ?string $unitPrice = null,
    ) {}
}
