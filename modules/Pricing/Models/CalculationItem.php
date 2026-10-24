<?php

declare(strict_types=1);

namespace Modules\Pricing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;

/**
 * Материален ред в записана калкулация.
 *
 * Полетата *_snapshot пазят състоянието на каталога към момента на записване.
 */
class CalculationItem extends Model
{
    protected $fillable = [
        'material_id',
        'unit_id',
        'material_name_snapshot',
        'sku_snapshot',
        'unit_name_snapshot',
        'unit_symbol_snapshot',
        'quantity',
        'input_mode',
        'entered_total_cost',
        'unit_price',
        'line_total',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'entered_total_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Calculation, $this> */
    public function calculation(): BelongsTo
    {
        return $this->belongsTo(Calculation::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
