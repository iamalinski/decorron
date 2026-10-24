<?php

declare(strict_types=1);

namespace Modules\Pricing\Services;

use RuntimeException;

/** Хвърля се, когато входните данни не позволяват валидна крайна цена. */
final class InvalidCalculationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'target_net_margin')
    {
        parent::__construct($message);
    }
}
