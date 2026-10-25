<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Models\ActivityLog;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Quote;
use Modules\Crm\Models\QuoteVersion;

/**
 * Създава и обновява оферти.
 *
 * Всички суми се смятат на сървъра с десетична аритметика — стойностите,
 * изпратени от frontend-а, се използват само като вход за редовете.
 */
final class QuoteWriter
{
    private const MONEY_SCALE = 2;

    public function __construct(private readonly ActivityRecorder $activities) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, Customer $customer, array $attributes): Quote
    {
        return DB::transaction(function () use ($user, $customer, $attributes): Quote {
            $issuedAt = Carbon::parse($attributes['issued_at'] ?? Carbon::today()->toDateString());

            $quote = new Quote($this->editableAttributes($attributes));
            $quote->user_id = $user->getKey();
            $quote->customer_id = $customer->getKey();
            $quote->quote_number = Quote::nextNumberFor($user, (int) $issuedAt->year);
            $quote->status = Quote::STATUS_DRAFT;
            $quote->version = 1;
            $quote->currency = (string) config('pricing.currency', 'EUR');
            $quote->vat_rate_snapshot = (string) config('pricing.taxes.vat', '0');

            $this->applyCustomerSnapshot($quote, $customer);
            $this->writeItemsAndTotals($quote, $attributes['items'] ?? []);

            if ($quote->crm_opportunity_id !== null) {
                $this->activities->log(
                    $user,
                    ActivityLog::TYPE_QUOTE_CREATED,
                    'Създадена оферта '.$quote->quote_number.' на стойност '.$this->money($quote->total, $quote->currency).'.',
                    $quote->opportunity,
                    customerId: $customer->getKey(),
                    meta: ['quote_id' => $quote->getKey()],
                );
            }

            return $quote->load('items');
        });
    }

    /**
     * Обновява оферта. Ако вече е била изпращана, създава нова версия —
     * изпратеното съдържание остава непроменено в quote_versions.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, Quote $quote, array $attributes): Quote
    {
        return DB::transaction(function () use ($quote, $attributes): Quote {
            $startsNewVersion = $quote->wasSent();

            $quote->fill($this->editableAttributes($attributes));

            if ($startsNewVersion) {
                $quote->version = $quote->version + 1;
                $quote->status = Quote::STATUS_DRAFT;
                $quote->sent_at = null;
                $quote->declined_at = null;
            }

            $this->writeItemsAndTotals($quote, $attributes['items'] ?? []);

            return $quote->load('items');
        });
    }

    /**
     * Маркира офертата като изпратена и замразява съдържанието ѝ.
     * Извиква се САМО след изрично потвърждение от потребителя.
     */
    public function markSent(User $user, Quote $quote): Quote
    {
        return DB::transaction(function () use ($user, $quote): Quote {
            $quote->loadMissing(['items', 'opportunity']);

            $sentAt = Carbon::now();

            QuoteVersion::query()->updateOrCreate(
                ['quote_id' => $quote->getKey(), 'version' => $quote->version],
                [
                    'user_id' => $user->getKey(),
                    'total' => (string) $quote->total,
                    'payload' => $this->snapshot($quote),
                    'sent_at' => $sentAt,
                ],
            );

            $quote->status = Quote::STATUS_SENT;
            $quote->sent_at = $sentAt;
            $quote->save();

            $this->activities->log(
                $user,
                ActivityLog::TYPE_QUOTE_SENT,
                'Оферта '.$quote->quote_number.' (версия '.$quote->version.') е отбелязана като изпратена.',
                $quote->opportunity,
                customerId: $quote->customer_id,
                automatic: false,
                meta: ['quote_id' => $quote->getKey(), 'version' => $quote->version],
            );

            return $quote;
        });
    }

    /** Отговор от клиента: приета или отказана. */
    public function recordReply(User $user, Quote $quote, string $status, ?string $note = null): Quote
    {
        $quote->loadMissing('opportunity');

        $quote->status = $status;
        $quote->accepted_at = $status === Quote::STATUS_ACCEPTED ? Carbon::now() : null;
        $quote->declined_at = $status === Quote::STATUS_DECLINED ? Carbon::now() : null;
        $quote->save();

        $this->activities->log(
            $user,
            $status === Quote::STATUS_ACCEPTED ? ActivityLog::TYPE_QUOTE_ACCEPTED : ActivityLog::TYPE_QUOTE_DECLINED,
            'Оферта '.$quote->quote_number.' е отбелязана като „'.$quote->statusLabel().'“.'
                .($note !== null && $note !== '' ? ' '.$note : ''),
            $quote->opportunity,
            customerId: $quote->customer_id,
            automatic: false,
            meta: ['quote_id' => $quote->getKey()],
        );

        return $quote;
    }

    /** @param array<string, mixed> $attributes */
    private function editableAttributes(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip([
            'crm_opportunity_id',
            'issued_at',
            'valid_until',
            'event_date',
            'event_location',
            'discount_type',
            'discount_value',
            'deposit_amount',
            'terms',
            'internal_notes',
        ]));
    }

    private function applyCustomerSnapshot(Quote $quote, Customer $customer): void
    {
        $quote->customer_name_snapshot = $customer->name;
        $quote->customer_contact_snapshot = $customer->contact_person;
        $quote->customer_phone_snapshot = $customer->phone;
        $quote->customer_email_snapshot = $customer->email;
        $quote->customer_address_snapshot = trim(implode(', ', array_filter([$customer->city, $customer->address]))) ?: null;
    }

    /**
     * Записва редовете и преизчислява сумите на сървъра.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function writeItemsAndTotals(Quote $quote, array $items): void
    {
        $subtotal = BigDecimal::zero();
        $rows = [];

        foreach (array_values($items) as $position => $item) {
            $quantity = BigDecimal::of((string) ($item['quantity'] ?? '1'));
            $unitPrice = BigDecimal::of((string) ($item['unit_price'] ?? '0'));
            $lineTotal = $quantity->multipliedBy($unitPrice)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);

            $subtotal = $subtotal->plus($lineTotal);

            $rows[] = [
                'calculation_id' => $item['calculation_id'] ?? null,
                'description' => trim((string) $item['description']),
                'details' => $item['details'] ?? null,
                'quantity' => (string) $quantity,
                'unit_price' => (string) $unitPrice->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP),
                'line_total' => (string) $lineTotal,
                'position' => $position,
            ];
        }

        $subtotal = $subtotal->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
        $discount = $this->discountAmount($subtotal, $quote->discount_type, $quote->discount_value);

        $quote->subtotal = (string) $subtotal;
        $quote->discount_amount = (string) $discount;
        $quote->total = (string) $subtotal->minus($discount)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
        $quote->save();

        $quote->items()->delete();

        foreach ($rows as $row) {
            $quote->items()->create($row);
        }
    }

    private function discountAmount(BigDecimal $subtotal, ?string $type, mixed $value): BigDecimal
    {
        if ($type === null || $value === null) {
            return BigDecimal::zero()->toScale(self::MONEY_SCALE);
        }

        $amount = $type === 'percent'
            ? $subtotal->multipliedBy(BigDecimal::of((string) $value))->dividedBy(100, self::MONEY_SCALE, RoundingMode::HALF_UP)
            : BigDecimal::of((string) $value)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);

        if ($amount->isGreaterThan($subtotal)) {
            return $subtotal;
        }

        return $amount->isNegative() ? BigDecimal::zero()->toScale(self::MONEY_SCALE) : $amount;
    }

    /**
     * Съдържанието на офертата, както е изпратено.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Quote $quote): array
    {
        return [
            'quote_number' => $quote->quote_number,
            'version' => $quote->version,
            'issued_at' => $quote->issued_at?->toDateString(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'customer' => [
                'name' => $quote->customer_name_snapshot,
                'contact' => $quote->customer_contact_snapshot,
                'phone' => $quote->customer_phone_snapshot,
                'email' => $quote->customer_email_snapshot,
                'address' => $quote->customer_address_snapshot,
            ],
            'event' => [
                'date' => $quote->event_date?->toDateString(),
                'location' => $quote->event_location,
            ],
            'items' => $quote->items->map(fn ($item): array => [
                'description' => $item->description,
                'details' => $item->details,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
            ])->all(),
            'subtotal' => (string) $quote->subtotal,
            'discount_type' => $quote->discount_type,
            'discount_value' => $quote->discount_value === null ? null : (string) $quote->discount_value,
            'discount_amount' => (string) $quote->discount_amount,
            'total' => (string) $quote->total,
            'deposit_amount' => $quote->deposit_amount === null ? null : (string) $quote->deposit_amount,
            'vat_rate' => (string) $quote->vat_rate_snapshot,
            'currency' => $quote->currency,
            'terms' => $quote->terms,
        ];
    }

    private function money(mixed $amount, string $currency): string
    {
        return number_format((float) $amount, 2, ',', ' ').' '.$currency;
    }
}
