<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Concerns\OwnedByUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Unit;

/**
 * Потребителски предпочитания за НОВИ калкулации.
 *
 * Тук НЯМА данъчни ставки — те се четат единствено от config/pricing.php.
 */
class UserSetting extends Model
{
    use OwnedByUser;

    public const ROUNDING_STRATEGIES = ['none', 'integer', 'ends_9', 'ends_5'];

    protected $table = 'user_settings';

    protected $fillable = [
        'default_net_margin',
        'default_rounding_strategy',
        'default_unit_id',
        'company_name',
        'company_phone',
        'company_email',
        'company_address',
        'company_extra',
        'default_quote_terms',
        'default_quote_validity_days',
    ];

    protected function casts(): array
    {
        return [
            'default_net_margin' => 'decimal:6',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_unit_id');
    }

    /**
     * Данните на бизнеса за клиентските документи.
     *
     * Когато името не е попълнено, се ползва името на приложението — така
     * офертата никога не излиза без подател.
     *
     * @return array<string, ?string>
     */
    public function companyDetails(): array
    {
        return [
            'name' => $this->company_name ?: (string) config('app.name'),
            'phone' => $this->company_phone,
            'email' => $this->company_email,
            'address' => $this->company_address,
            'extra' => $this->company_extra,
        ];
    }

    /** Настройките на потребителя, създадени при първо обръщение. */
    public static function forUser(User $user): self
    {
        return static::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'default_net_margin' => (string) config('pricing.defaults.net_margin'),
                'default_rounding_strategy' => (string) config('pricing.defaults.rounding_strategy'),
            ],
        );
    }
}
