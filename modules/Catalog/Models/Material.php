<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Concerns\OwnedByUser;
use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\MaterialFactory;
use Modules\Pricing\Models\CalculationItem;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $sku
 * @property string|null $category
 * @property int $unit_id
 * @property string $default_unit_price
 * @property string|null $description
 * @property bool $is_active
 */
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    use OwnedByUser;
    use Trashable;

    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit_id',
        'default_unit_price',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'default_unit_price' => 'decimal:6',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Редовете на записаните калкулации, които сочат към този материал.
     *
     * Редът пази собствен snapshot на името, кода и цената, така че остава
     * четим и след като материалът изчезне — връзката е само информативна.
     *
     * @return HasMany<CalculationItem, $this>
     */
    public function calculationItems(): HasMany
    {
        return $this->hasMany(CalculationItem::class);
    }

    /** В колко записани реда се използва материалът. */
    public function usageCount(): int
    {
        return $this->calculationItems()->count();
    }

    /**
     * Материалът никога не блокира изтриването.
     *
     * Каталогът стои „отстрани“ на веригата клиент → запитване → оферта:
     * никой запис не зависи от него, за да бъде четим. Затова изтриването
     * винаги е възможно — контролерът само избира дали да е окончателно
     * (неизползван материал) или в Кошчето (използван).
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public function deletionBlockers(bool $includeTrashed = false): array
    {
        return [];
    }

    /** @return array{subject: string, deleted: string, restored: string, pronoun: string} */
    public static function trashWording(): array
    {
        return [
            'subject' => 'Материалът',
            'deleted' => 'изтрит',
            'restored' => 'възстановен',
            'pronoun' => 'него',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('sku', 'like', '%'.$term.'%')
                ->orWhere('category', 'like', '%'.$term.'%');
        });
    }

    protected static function newFactory(): MaterialFactory
    {
        return MaterialFactory::new();
    }
}
