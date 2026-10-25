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
use Modules\Crm\Models\Order;
use Modules\Crm\Models\Quote;

/**
 * Създава и обновява поръчки.
 *
 * Една приета оферта поражда най-много една поръчка: повторното потвърждение
 * връща вече създадената, вместо да прави дубликат.
 */
final class OrderWriter
{
    private const MONEY_SCALE = 2;

    public function __construct(private readonly ActivityRecorder $activities) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, Customer $customer, array $attributes): Order
    {
        return DB::transaction(function () use ($user, $customer, $attributes): Order {
            $quoteId = $attributes['quote_id'] ?? null;

            // Защита срещу дублиране при повторно приемане на същата оферта.
            // Проверява се и Кошчето — изтритата поръчка се връща обратно,
            // защото уникалният индекс пази само една поръчка на оферта.
            if ($quoteId !== null) {
                $existing = Order::query()->withTrashed()->ownedBy($user)->where('quote_id', (int) $quoteId)->first();

                if ($existing !== null) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    return $existing->load('items');
                }
            }

            $eventDate = isset($attributes['event_date']) && $attributes['event_date'] !== null
                ? Carbon::parse($attributes['event_date'])
                : Carbon::today();

            $order = new Order(array_intersect_key($attributes, array_flip([
                'crm_opportunity_id',
                'quote_id',
                'quote_version',
                'event_date',
                'event_location',
                'deposit_amount',
                'execution_notes',
                'deadline_note',
            ])));

            $order->user_id = $user->getKey();
            $order->customer_id = $customer->getKey();
            $order->order_number = Order::nextNumberFor($user, (int) $eventDate->year);
            $order->status = $attributes['status'] ?? Order::STATUS_CONFIRMED;
            $order->currency = (string) config('pricing.currency', 'EUR');
            $order->customer_name_snapshot = $customer->name;
            $order->customer_phone_snapshot = $customer->phone;
            $order->customer_email_snapshot = $customer->email;
            $order->confirmed_at = $order->status === Order::STATUS_PENDING ? null : Carbon::now();

            $this->writeItemsAndTotal($order, $attributes['items'] ?? []);

            $this->activities->log(
                $user,
                ActivityLog::TYPE_ORDER_CREATED,
                'Създадена поръчка '.$order->order_number.' на стойност '
                    .number_format((float) $order->agreed_total, 2, ',', ' ').' '.$order->currency.'.',
                $order->opportunity,
                customerId: $customer->getKey(),
                meta: ['order_id' => $order->getKey()],
            );

            return $order->load('items');
        });
    }

    /** Поръчка от приета оферта — редовете се копират от офертата. */
    public function createFromQuote(User $user, Quote $quote, array $attributes = []): Order
    {
        $quote->loadMissing(['items', 'customer']);

        $items = $quote->items->map(fn ($item): array => [
            'description' => $item->description,
            'details' => $item->details,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
        ])->all();

        return $this->create($user, $quote->customer, array_merge([
            'quote_id' => $quote->getKey(),
            'quote_version' => (int) $quote->version,
            'crm_opportunity_id' => $quote->crm_opportunity_id,
            'event_date' => $quote->event_date?->toDateString(),
            'event_location' => $quote->event_location,
            'deposit_amount' => $quote->deposit_amount === null ? null : (string) $quote->deposit_amount,
            'items' => $items,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $user, Order $order, array $attributes): Order
    {
        return DB::transaction(function () use ($order, $attributes): Order {
            $order->fill(array_intersect_key($attributes, array_flip([
                'event_date',
                'event_location',
                'deposit_amount',
                'execution_notes',
                'deadline_note',
            ])));

            $this->writeItemsAndTotal($order, $attributes['items'] ?? []);

            return $order->load('items');
        });
    }

    public function changeStatus(User $user, Order $order, string $status): Order
    {
        $previous = $order->status;

        $order->status = $status;
        $order->confirmed_at = $order->confirmed_at ?? ($status === Order::STATUS_PENDING ? null : Carbon::now());
        $order->completed_at = $status === Order::STATUS_COMPLETED ? Carbon::now() : null;
        $order->cancelled_at = $status === Order::STATUS_CANCELLED ? Carbon::now() : null;
        $order->save();

        $order->loadMissing('opportunity');

        $this->activities->log(
            $user,
            ActivityLog::TYPE_ORDER_CREATED,
            'Поръчка '.$order->order_number.': статусът е променен на „'.$order->statusLabel().'“.',
            $order->opportunity,
            customerId: $order->customer_id,
            meta: ['order_id' => $order->getKey(), 'from' => $previous, 'to' => $status],
        );

        return $order;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function writeItemsAndTotal(Order $order, array $items): void
    {
        $total = BigDecimal::zero();
        $rows = [];

        foreach (array_values($items) as $position => $item) {
            $quantity = BigDecimal::of((string) ($item['quantity'] ?? '1'));
            $unitPrice = BigDecimal::of((string) ($item['unit_price'] ?? '0'));
            $lineTotal = $quantity->multipliedBy($unitPrice)->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);

            $total = $total->plus($lineTotal);

            $rows[] = [
                'description' => trim((string) $item['description']),
                'details' => $item['details'] ?? null,
                'quantity' => (string) $quantity,
                'unit_price' => (string) $unitPrice->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP),
                'line_total' => (string) $lineTotal,
                'position' => $position,
            ];
        }

        $order->agreed_total = (string) $total->toScale(self::MONEY_SCALE, RoundingMode::HALF_UP);
        $order->save();

        $order->items()->delete();

        foreach ($rows as $row) {
            $order->items()->create($row);
        }
    }
}
