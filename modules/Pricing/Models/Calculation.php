<?php

declare(strict_types=1);

namespace Modules\Pricing\Models;

use App\Concerns\OwnedByUser;
use App\Concerns\Trashable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Pricing\Database\Factories\CalculationFactory;

/**
 * Записана калкулация. Всички стойности са snapshot към момента на записване
 * и никога не се преизчисляват спрямо текущия каталог или настройки.
 */
class Calculation extends Model
{
    /** @use HasFactory<CalculationFactory> */
    use HasFactory;

    use OwnedByUser;
    use Trashable;

    protected $fillable = [
        'calculation_number',
        'service_name',
        'client_name',
        'notes',
        'calculated_at',
        'material_cost_total',
        'labor_cost',
        'other_costs',
        'total_cost',
        'target_net_margin',
        'corporate_tax_rate_snapshot',
        'dividend_tax_rate_snapshot',
        'vat_rate_snapshot',
        'profit_before_tax',
        'corporate_tax_amount',
        'profit_after_corporate_tax',
        'dividend_tax_amount',
        'net_profit',
        'final_price',
        'actual_net_margin',
        'price_was_rounded',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'calculated_at' => 'date',
            'price_was_rounded' => 'boolean',
            'material_cost_total' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'target_net_margin' => 'decimal:6',
            'corporate_tax_rate_snapshot' => 'decimal:6',
            'dividend_tax_rate_snapshot' => 'decimal:6',
            'vat_rate_snapshot' => 'decimal:6',
            'profit_before_tax' => 'decimal:2',
            'corporate_tax_amount' => 'decimal:2',
            'profit_after_corporate_tax' => 'decimal:2',
            'dividend_tax_amount' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'final_price' => 'decimal:2',
            'actual_net_margin' => 'decimal:6',
        ];
    }

    /** @return HasMany<CalculationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CalculationItem::class)->orderBy('position');
    }

    /**
     * Връзка към CRM. Колоните се попълват от Modules\Crm\Services\CalculationLinker
     * и са nullable — калкулация без клиент е напълно валидна.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Opportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'crm_opportunity_id');
    }

    /** @return array{subject: string, deleted: string, restored: string, pronoun: string} */
    public static function trashWording(): array
    {
        return ['subject' => 'Калкулацията', 'deleted' => 'изтрита', 'restored' => 'възстановена', 'pronoun' => 'нея'];
    }

    /**
     * Калкулацията е листо във веригата: редовете ѝ се изтриват заедно с нея,
     * а офертите пазят собствено копие на описанията и цените, затова нищо
     * не пречи на изтриването ѝ.
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
        ]));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('service_name', 'like', '%'.$term.'%')
                ->orWhere('client_name', 'like', '%'.$term.'%')
                ->orWhere('calculation_number', 'like', '%'.$term.'%');
        });
    }

    /**
     * Следващият пореден номер за потребителя, във формат "K-2026-0001".
     * Използва се вътре в транзакция с заключен ред, за да няма дублиране.
     */
    public static function nextNumberFor(User $user, int $year): string
    {
        $prefix = 'K-'.$year.'-';

        $last = static::query()
            ->ownedBy($user)
            ->withTrashed()->where('calculation_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('calculation_number')
            ->value('calculation_number');

        $sequence = $last !== null ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    protected static function newFactory(): CalculationFactory
    {
        return CalculationFactory::new();
    }
}
