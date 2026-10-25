<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Models\User;
use Modules\Crm\Models\ActivityLog;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Pricing\Models\Calculation;

/**
 * Свързва записана калкулация с CRM.
 *
 * Правила (вж. заданието, раздел „Автоматична връзка“):
 *  - Без избран клиент не се създава нищо в CRM.
 *  - Избрано конкретно запитване → калкулацията се прикача към него.
 *  - Изрично поискано ново запитване → създава се на етап „Изготвяне на цена“.
 *  - Иначе: ако клиентът има точно едно отворено запитване, калкулацията се
 *    прикача към него. При повече от едно системата не гадае и оставя
 *    калкулацията несвързана — потребителят избира по-късно.
 *
 * Никога не се записва, че е проведена консултация или че е изпратена оферта.
 */
final class CalculationLinker
{
    public function __construct(private readonly ActivityRecorder $activities) {}

    /**
     * @param  array<string, mixed>  $attributes  валидираните данни от заявката
     * @return array{customer: ?Customer, opportunity: ?Opportunity, opportunity_was_created: bool}
     */
    public function link(User $user, Calculation $calculation, array $attributes): array
    {
        $customer = $this->resolveCustomer($user, $attributes);

        if ($customer === null) {
            return ['customer' => null, 'opportunity' => null, 'opportunity_was_created' => false];
        }

        $calculation->customer_id = $customer->getKey();

        // Името на клиента се пази като snapshot към момента на записване.
        if (trim((string) $calculation->client_name) === '') {
            $calculation->client_name = $customer->name;
        }

        [$opportunity, $wasCreated] = $this->resolveOpportunity($user, $customer, $calculation, $attributes);

        $calculation->crm_opportunity_id = $opportunity?->getKey();
        $calculation->save();

        if ($opportunity !== null) {
            $this->activities->log(
                $user,
                ActivityLog::TYPE_CALCULATION_LINKED,
                'Добавена калкулация '.$calculation->calculation_number
                    .' („'.$calculation->service_name.'“) на стойност '
                    .number_format((float) $calculation->final_price, 2, ',', ' ').' '.$calculation->currency.'.',
                $opportunity,
                meta: ['calculation_id' => $calculation->getKey()],
            );
        }

        return ['customer' => $customer, 'opportunity' => $opportunity, 'opportunity_was_created' => $wasCreated];
    }

    /** @param array<string, mixed> $attributes */
    private function resolveCustomer(User $user, array $attributes): ?Customer
    {
        $customerId = $attributes['customer_id'] ?? null;

        if ($customerId === null) {
            return null;
        }

        return Customer::query()->ownedBy($user)->whereKey((int) $customerId)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: ?Opportunity, 1: bool}
     */
    private function resolveOpportunity(User $user, Customer $customer, Calculation $calculation, array $attributes): array
    {
        $opportunityId = $attributes['opportunity_id'] ?? null;

        if ($opportunityId !== null) {
            $opportunity = Opportunity::query()
                ->ownedBy($user)
                ->where('customer_id', $customer->getKey())
                ->whereKey((int) $opportunityId)
                ->first();

            return [$opportunity, false];
        }

        if (filter_var($attributes['create_opportunity'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [$this->createOpportunity($user, $customer, $calculation), true];
        }

        // Сценарий А: точно едно отворено запитване → прикачаме към него.
        $open = Opportunity::query()
            ->ownedBy($user)
            ->where('customer_id', $customer->getKey())
            ->open()
            ->limit(2)
            ->get();

        return [$open->count() === 1 ? $open->first() : null, false];
    }

    private function createOpportunity(User $user, Customer $customer, Calculation $calculation): Opportunity
    {
        $opportunity = new Opportunity([
            'customer_id' => $customer->getKey(),
            'title' => $calculation->service_name,
            'source' => $customer->source,
            // Калкулацията е част от изготвянето на цена — но не твърдим,
            // че е имало запитване или проведена консултация.
            'stage' => Opportunity::STAGE_PRICING,
            'status' => Opportunity::STATUS_OPEN,
        ]);

        $opportunity->user_id = $user->getKey();
        $opportunity->save();

        $this->activities->log(
            $user,
            ActivityLog::TYPE_OPPORTUNITY_CREATED,
            'Създадено запитване „'.$opportunity->title.'“ от калкулация '.$calculation->calculation_number.'.',
            $opportunity,
        );

        return $opportunity;
    }
}
