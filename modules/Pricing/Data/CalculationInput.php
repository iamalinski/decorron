<?php

declare(strict_types=1);

namespace Modules\Pricing\Data;

/**
 * Валидирани входни данни за калкулация. Създава се само от Form Request,
 * така че service-ът никога не работи с необработен потребителски вход.
 */
final readonly class CalculationInput
{
    /**
     * @param  list<CalculationItemInput>  $items
     */
    public function __construct(
        public array $items,
        public string $laborCost,
        public string $otherCosts,
        public string $targetNetMargin,
        public ?string $finalPriceOverride = null,
    ) {}
}
