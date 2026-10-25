<?php

declare(strict_types=1);

namespace Modules\Crm\Models;

use App\Concerns\OwnedByUser;
use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Crm\Support\CrmOptions;

/**
 * Задача или напомняне вътре в приложението.
 *
 * Няма изпращане на имейл, SMS или push — в тази версия напомнянията живеят
 * само в приложението.
 */
class Task extends Model
{
    use OwnedByUser;
    use Trashable;

    protected $table = 'crm_tasks';

    protected $fillable = [
        'customer_id',
        'crm_opportunity_id',
        'quote_id',
        'order_id',
        'title',
        'description',
        'type',
        'due_on',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completed_at' => 'datetime',
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

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /** Незавършени задачи с изминал срок. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_on')->whereDate('due_on', '<', Carbon::today()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->completed_at === null
            && $this->due_on !== null
            && $this->due_on->isBefore(Carbon::today());
    }

    public function typeLabel(): string
    {
        return CrmOptions::TASK_TYPES[$this->type] ?? $this->type;
    }

    /** @return array{subject: string, deleted: string, restored: string, pronoun: string} */
    public static function trashWording(): array
    {
        return ['subject' => 'Задачата', 'deleted' => 'изтрита', 'restored' => 'възстановена', 'pronoun' => 'нея'];
    }

    /**
     * Задачата е последното звено във веригата — нищо не сочи към нея.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function deletionBlockers(bool $includeTrashed = false): array
    {
        return [];
    }

    /** @return array<int, string> */
    public function restoreBlockers(): array
    {
        return array_values(array_filter([
            $this->trashedParent($this->customer(), 'клиента'),
            $this->trashedParent($this->opportunity(), 'запитването'),
            $this->trashedParent($this->quote(), 'офертата'),
            $this->trashedParent($this->order(), 'поръчката'),
        ]));
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
