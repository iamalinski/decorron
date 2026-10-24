<?php

declare(strict_types=1);

namespace Modules\Pricing\Support;

use Brick\Math\BigDecimal;

/**
 * Immutable снимка на данъчните ставки, прочетени от config/pricing.php.
 *
 * Класът е единственият начин, по който останалата част от приложението
 * получава достъп до ставките. Няма setter-и и няма път от HTTP заявка
 * до промяна на стойностите.
 */
final readonly class TaxRates
{
    private function __construct(
        public BigDecimal $corporate,
        public BigDecimal $dividend,
        public BigDecimal $vat,
    ) {}

    /** Текущите ставки, конфигурирани в приложението. */
    public static function current(): self
    {
        return new self(
            BigDecimal::of((string) config('pricing.taxes.corporate')),
            BigDecimal::of((string) config('pricing.taxes.dividend')),
            BigDecimal::of((string) config('pricing.taxes.vat')),
        );
    }

    /** Ставки, възстановени от записана калкулация (за историческа коректност). */
    public static function fromSnapshot(string $corporate, string $dividend, string $vat): self
    {
        return new self(
            BigDecimal::of($corporate),
            BigDecimal::of($dividend),
            BigDecimal::of($vat),
        );
    }

    /**
     * Делът от печалбата преди данъци, който остава след корпоративния данък
     * и данъка върху дивидента: (1 - t_c) * (1 - t_d).
     */
    public function netFactor(): BigDecimal
    {
        $one = BigDecimal::one();

        return $one->minus($this->corporate)
            ->multipliedBy($one->minus($this->dividend));
    }

    /** @return array{corporate: string, dividend: string, vat: string} */
    public function toArray(): array
    {
        return [
            'corporate' => (string) $this->corporate,
            'dividend' => (string) $this->dividend,
            'vat' => (string) $this->vat,
        ];
    }
}
