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
 * Потвърдена работа за изпълнение.
 */
class Order extends Model
{
    use OwnedByUser;
    use Trashable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id',
        'crm_opportunity_id',
        'quote_id',
        'quote_version',
        'status',
        'event_date',
        'event_location',
        'deposit_amount',
        'execution_notes',
        'deadline_note',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'agreed_total' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
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

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return array{subject: string, deleted: string, restored: string, pronoun: string} */
    public static function trashWording(): array
    {
        return ['subject' => 'Поръчката', 'deleted' => 'изтрита', 'restored' => 'възстановена', 'pronoun' => 'нея'];
    }

    /**
     * Редовете на поръчката следват самата поръчка.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function deletionBlockers(bool $includeTrashed = false): array
    {
        $tasks = $includeTrashed ? $this->tasks()->withTrashed()->count() : $this->tasks()->count();

        return self::onlyBlocking([
            self::blocker('tasks', $tasks, 'задача', 'задачи'),
        ]);
    }

    /** @return array<int, string> */
    public function restoreBlockers(): array
    {
        return array_values(array_filter([
            $this->trashedParent($this->customer(), 'клиента'),
            $this->trashedParent($this->opportunity(), 'запитването'),
            $this->trashedParent($this->quote(), 'офертата'),
        ]));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('order_number', 'like', '%'.$term.'%')
                ->orWhere('customer_name_snapshot', 'like', '%'.$term.'%');
        });
    }

    /** Поръчки, които още предстоят. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function statusLabel(): string
    {
        return CrmOptions::ORDER_STATUSES[$this->status] ?? $this->status;
    }

    /** Следващият пореден номер за потребителя, във формат „P-2026-0001“. */
    public static function nextNumberFor(User $user, int $year): string
    {
        $prefix = 'P-'.$year.'-';

        $last = static::query()
            ->ownedBy($user)
            ->withTrashed()->where('order_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = $last !== null ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
