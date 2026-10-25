<?php

declare(strict_types=1);

namespace Modules\Crm\Models;

use App\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Замразено копие на офертата в момента на изпращане. Не се променя никога.
 */
class QuoteVersion extends Model
{
    use OwnedByUser;

    protected $fillable = [
        'quote_id',
        'version',
        'total',
        'payload',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Quote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
