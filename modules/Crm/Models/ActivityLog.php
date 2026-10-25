<?php

declare(strict_types=1);

namespace Modules\Crm\Models;

use App\Concerns\OwnedByUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запис в хронологията на клиент / възможност.
 *
 * Автоматичните записи се създават само когато системата има достоверни
 * данни за действието (напр. реално записана калкулация). Всичко, което
 * потребителят твърди сам (проведен разговор, изпратена оферта), се записва
 * с is_automatic = false.
 */
class ActivityLog extends Model
{
    use OwnedByUser;

    protected $table = 'crm_activity_logs';

    public const TYPE_OPPORTUNITY_CREATED = 'opportunity_created';

    public const TYPE_STAGE_CHANGED = 'stage_changed';

    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_NOTE = 'note';

    public const TYPE_CALCULATION_LINKED = 'calculation_linked';

    public const TYPE_CALCULATION_UPDATED = 'calculation_updated';

    public const TYPE_QUOTE_CREATED = 'quote_created';

    public const TYPE_QUOTE_SENT = 'quote_sent';

    public const TYPE_QUOTE_ACCEPTED = 'quote_accepted';

    public const TYPE_QUOTE_DECLINED = 'quote_declined';

    public const TYPE_ORDER_CREATED = 'order_created';

    protected $fillable = [
        'customer_id',
        'opportunity_id',
        'type',
        'description',
        'is_automatic',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_automatic' => 'boolean',
            'meta' => 'array',
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
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }
}
