<?php

declare(strict_types=1);

namespace Modules\Crm\Models;

use App\Concerns\OwnedByUser;
use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Database\Factories\CustomerFactory;
use Modules\Crm\Support\CrmOptions;
use Modules\Pricing\Models\Calculation;

/**
 * Клиент — физическо лице или организация.
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use OwnedByUser;
    use Trashable;

    protected $fillable = [
        'type',
        'name',
        'contact_person',
        'phone',
        'email',
        'city',
        'address',
        'source',
        'notes',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // phone_normalized се поддържа автоматично и никога не се приема от клиента.
        static::saving(function (self $customer): void {
            $customer->phone_normalized = self::normalizePhone($customer->phone);
        });
    }

    /**
     * Ключ за сравняване на телефони.
     *
     * Взимат се последните 9 цифри, защото един и същ български номер се
     * записва по няколко начина: 0888111222, +359888111222, 00359888111222.
     * По-късите номера се запазват цели.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits === '' ? null : substr($digits, -9);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $digits = self::normalizePhone($term);

        return $query->where(function (Builder $q) use ($term, $digits): void {
            $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('contact_person', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%');

            if ($digits !== null) {
                $q->orWhere('phone_normalized', 'like', '%'.$digits.'%');
            }
        });
    }

    /** @return HasMany<Opportunity, $this> */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
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

    /** @return HasMany<Calculation, $this> */
    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class);
    }

    /** @return array{subject: string, deleted: string, restored: string, pronoun: string} */
    public static function trashWording(): array
    {
        return ['subject' => 'Клиентът', 'deleted' => 'изтрит', 'restored' => 'възстановен', 'pronoun' => 'него'];
    }

    /**
     * Клиентът се изтрива последен — чак когато нищо друго не сочи към него.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function deletionBlockers(bool $includeTrashed = false): array
    {
        $count = static fn (HasMany $relation): int => $includeTrashed
            ? $relation->withTrashed()->count()
            : $relation->count();

        return self::onlyBlocking([
            self::blocker('opportunities', $count($this->opportunities()), 'запитване', 'запитвания'),
            self::blocker('quotes', $count($this->quotes()), 'оферта', 'оферти'),
            self::blocker('orders', $count($this->orders()), 'поръчка', 'поръчки'),
            self::blocker('calculations', $count($this->calculations()), 'калкулация', 'калкулации'),
            self::blocker('tasks', $count($this->tasks()), 'задача', 'задачи'),
        ]);
    }

    public function typeLabel(): string
    {
        return CrmOptions::CUSTOMER_TYPES[$this->type] ?? $this->type;
    }

    public function sourceLabel(): ?string
    {
        return $this->source === null ? null : (CrmOptions::SOURCES[$this->source] ?? $this->source);
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
