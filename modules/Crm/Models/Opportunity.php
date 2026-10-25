<?php

declare(strict_types=1);

namespace Modules\Crm\Models;

use App\Concerns\OwnedByUser;
use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Database\Factories\OpportunityFactory;
use Modules\Crm\Support\CrmOptions;
use Modules\Pricing\Models\Calculation;

/**
 * Възможност — конкретна потенциална продажба за един клиент.
 */
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory;

    use OwnedByUser;
    use Trashable;

    protected $table = 'crm_opportunities';

    public const STATUS_OPEN = 'open';

    public const STATUS_WON = 'won';

    public const STATUS_LOST = 'lost';

    public const STAGE_NEW = 'new';

    public const STAGE_PRICING = 'pricing';

    public const STAGE_QUOTE_SENT = 'quote_sent';

    protected $fillable = [
        'customer_id',
        'title',
        'event_type',
        'event_date',
        'event_location',
        'expected_budget',
        'source',
        'stage',
        'status',
        'lost_reason',
        'lost_reason_note',
        'next_action',
        'next_action_due',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'next_action_due' => 'date',
            'closed_at' => 'datetime',
            'expected_budget' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'opportunity_id')->latest();
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'crm_opportunity_id');
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'crm_opportunity_id');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'crm_opportunity_id');
    }

    /** @return HasMany<Calculation, $this> */
    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class, 'crm_opportunity_id');
    }

    /** @return array{subject: string, deleted: string, restored: string, pronoun: string} */
    public static function trashWording(): array
    {
        return ['subject' => 'Запитването', 'deleted' => 'изтрито', 'restored' => 'възстановено', 'pronoun' => 'него'];
    }

    /**
     * Запитването се изтрива, след като офертите, поръчките, калкулациите и
     * задачите по него са изтрити. Историята (`crm_activity_logs`) следва
     * запитването автоматично.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function deletionBlockers(bool $includeTrashed = false): array
    {
        $count = static fn (HasMany $relation): int => $includeTrashed
            ? $relation->withTrashed()->count()
            : $relation->count();

        return self::onlyBlocking([
            self::blocker('quotes', $count($this->quotes()), 'оферта', 'оферти'),
            self::blocker('orders', $count($this->orders()), 'поръчка', 'поръчки'),
            self::blocker('calculations', $count($this->calculations()), 'калкулация', 'калкулации'),
            self::blocker('tasks', $count($this->tasks()), 'задача', 'задачи'),
        ]);
    }

    /** @return array<int, string> */
    public function restoreBlockers(): array
    {
        return array_values(array_filter([
            $this->trashedParent($this->customer(), 'клиента'),
        ]));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', '%'.$term.'%')
                ->orWhere('event_type', 'like', '%'.$term.'%')
                ->orWhere('event_location', 'like', '%'.$term.'%')
                ->orWhereHas('customer', fn (Builder $c) => $c->search($term));
        });
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function stageLabel(): string
    {
        return CrmOptions::STAGES[$this->stage] ?? $this->stage;
    }

    public function statusLabel(): string
    {
        return CrmOptions::STATUSES[$this->status] ?? $this->status;
    }

    public function lostReasonLabel(): ?string
    {
        return $this->lost_reason === null ? null : (CrmOptions::LOST_REASONS[$this->lost_reason] ?? $this->lost_reason);
    }

    protected static function newFactory(): OpportunityFactory
    {
        return OpportunityFactory::new();
    }
}
