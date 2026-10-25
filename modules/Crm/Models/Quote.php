<?php

declare(strict_types=1);

namespace Modules\Crm\Models;

use App\Concerns\OwnedByUser;
use App\Concerns\Trashable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Support\CrmOptions;

/**
 * Оферта към клиента — клиентски документ със собствен номер, статус и версии.
 */
class Quote extends Model
{
    use OwnedByUser;
    use Trashable;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'customer_id',
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
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
            'event_date' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'vat_rate_snapshot' => 'decimal:6',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'crm_opportunity_id');
    }

    /** @return HasMany<QuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    /** @return HasMany<QuoteVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class)->orderByDesc('version');
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return array{subject: string, deleted: string, restored: string, pronoun: string} */
    public static function trashWording(): array
    {
        return ['subject' => 'Офертата', 'deleted' => 'изтрита', 'restored' => 'възстановена', 'pronoun' => 'нея'];
    }

    /**
     * Редовете и версиите следват офертата, затова не пречат на изтриването.
     * Поръчка и задача обаче са самостоятелни записи.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function deletionBlockers(bool $includeTrashed = false): array
    {
        $count = static fn (HasMany $relation): int => $includeTrashed
            ? $relation->withTrashed()->count()
            : $relation->count();

        return self::onlyBlocking([
            self::blocker('orders', $count($this->orders()), 'поръчка', 'поръчки'),
            self::blocker('tasks', $count($this->tasks()), 'задача', 'задачи'),
        ]);
    }

    /** @return array<int, string> */
    public function restoreBlockers(): array
    {
        return array_values(array_filter([
            $this->trashedParent($this->customer(), 'клиента'),
            $this->trashedParent($this->opportunity(), 'запитването'),
        ]));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('quote_number', 'like', '%'.$term.'%')
                ->orWhere('customer_name_snapshot', 'like', '%'.$term.'%');
        });
    }

    /** Чака отговор от клиента. */
    public function scopeAwaitingReply(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function statusLabel(): string
    {
        return CrmOptions::QUOTE_STATUSES[$this->status] ?? $this->status;
    }

    /** Приетата оферта не се редактира — тя вече е договорка. */
    public function isEditable(): bool
    {
        return $this->status !== self::STATUS_ACCEPTED;
    }

    /** Изпратената версия е замразена и промяната ѝ изисква нова версия. */
    public function wasSent(): bool
    {
        return $this->sent_at !== null;
    }

    /** Следващият пореден номер за потребителя, във формат „OF-2026-0001“. */
    public static function nextNumberFor(User $user, int $year): string
    {
        $prefix = 'OF-'.$year.'-';

        $last = static::query()
            ->ownedBy($user)
            ->withTrashed()->where('quote_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('quote_number')
            ->value('quote_number');

        $sequence = $last !== null ? ((int) mb_substr((string) $last, mb_strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
