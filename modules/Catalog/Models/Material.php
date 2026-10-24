<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\MaterialFactory;

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
