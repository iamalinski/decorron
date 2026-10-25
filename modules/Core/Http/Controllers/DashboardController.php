<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalog\Http\Resources\MaterialResource;
use Modules\Catalog\Models\Material;
use Modules\Crm\Http\Resources\OpportunityResource;
use Modules\Crm\Http\Resources\OrderResource;
use Modules\Crm\Http\Resources\QuoteResource;
use Modules\Crm\Http\Resources\TaskResource;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Order;
use Modules\Crm\Models\Quote;
use Modules\Crm\Models\Task;
use Modules\Crm\Support\CrmOptions;
use Modules\Pricing\Http\Resources\CalculationResource;
use Modules\Pricing\Models\Calculation;

/**
 * Таблото обединява ценообразуването и CRM.
 *
 * Всички показатели се извеждат от реални записи. Договорените суми по
 * поръчки НЕ се представят като реализиран приход — отчитането на плащания
 * не е част от тази версия.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $horizon = $today->copy()->addDays(30);

        $recentCalculations = Calculation::query()
            ->ownedBy($user)
            ->withCount('items')
            ->with(['customer', 'opportunity'])
            ->orderByDesc('calculated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentOpportunities = Opportunity::query()
            ->ownedBy($user)
            ->with('customer')
            ->open()
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $quotesAwaiting = Quote::query()
            ->ownedBy($user)
            ->awaitingReply()
            ->orderByRaw('valid_until is null, valid_until')
            ->limit(5)
            ->get();

        $upcomingOrders = Order::query()
            ->ownedBy($user)
            ->active()
            ->whereNotNull('event_date')
            ->whereBetween('event_date', [$today->toDateString(), $horizon->toDateString()])
            ->orderBy('event_date')
            ->limit(5)
            ->get();

        $overdueTasks = Task::query()
            ->ownedBy($user)
            ->with('customer')
            ->overdue()
            ->orderBy('due_on')
            ->limit(5)
            ->get();

        // Отворени запитвания без следващо действие — най-честата причина
        // клиент да бъде забравен.
        $withoutNextAction = Opportunity::query()
            ->ownedBy($user)
            ->with('customer')
            ->open()
            ->where(function ($query): void {
                $query->whereNull('next_action')->orWhere('next_action', '');
            })
            ->orderBy('id')
            ->limit(5)
            ->get();

        return Inertia::render('core::dashboard', [
            'stats' => [
                'calculations_this_month' => Calculation::query()->ownedBy($user)
                    ->whereDate('calculated_at', '>=', $monthStart->toDateString())->count(),
                'calculations_total' => Calculation::query()->ownedBy($user)->count(),
                'new_opportunities_this_month' => Opportunity::query()->ownedBy($user)
                    ->where('created_at', '>=', $monthStart)->count(),
                'open_opportunities' => Opportunity::query()->ownedBy($user)->open()->count(),
                'quotes_awaiting' => Quote::query()->ownedBy($user)->awaitingReply()->count(),
                'won_this_month' => Opportunity::query()->ownedBy($user)
                    ->where('status', Opportunity::STATUS_WON)
                    ->where('closed_at', '>=', $monthStart)->count(),
                'upcoming_events' => Order::query()->ownedBy($user)->active()
                    ->whereNotNull('event_date')
                    ->whereBetween('event_date', [$today->toDateString(), $horizon->toDateString()])->count(),
                'overdue_tasks' => Task::query()->ownedBy($user)->overdue()->count(),
                'materials_count' => Material::query()->ownedBy($user)->active()->count(),
                // Договорена (не получена) стойност на предстоящите поръчки.
                'committed_upcoming_total' => (string) (Order::query()->ownedBy($user)->active()
                    ->whereNotNull('event_date')
                    ->whereBetween('event_date', [$today->toDateString(), $horizon->toDateString()])
                    ->sum('agreed_total') ?: '0'),
                'currency' => (string) config('pricing.currency', 'EUR'),
            ],

            'pipeline' => $this->pipeline($user),

            'recentCalculations' => CalculationResource::collection($recentCalculations),
            'recentOpportunities' => OpportunityResource::collection($recentOpportunities),
            'quotesAwaiting' => QuoteResource::collection($quotesAwaiting),
            'upcomingOrders' => OrderResource::collection($upcomingOrders),
            'overdueTasks' => TaskResource::collection($overdueTasks),
            'opportunitiesWithoutNextAction' => OpportunityResource::collection($withoutNextAction),
            'frequentMaterials' => MaterialResource::collection($this->frequentMaterials($user)),
        ]);
    }

    /**
     * Брой отворени запитвания по етапи — за хоризонталната диаграма.
     *
     * @return array<int, array{stage: string, label: string, count: int}>
     */
    private function pipeline($user): array
    {
        $counts = Opportunity::query()
            ->ownedBy($user)
            ->open()
            ->select('stage', DB::raw('count(*) as total'))
            ->groupBy('stage')
            ->pluck('total', 'stage');

        return array_map(
            static fn (string $stage, string $label): array => [
                'stage' => $stage,
                'label' => $label,
                'count' => (int) ($counts[$stage] ?? 0),
            ],
            array_keys(CrmOptions::STAGES),
            array_values(CrmOptions::STAGES),
        );
    }

    /** Най-често използваните материали, изведени от реалните редове. */
    private function frequentMaterials($user)
    {
        $frequentIds = DB::table('calculation_items')
            ->join('calculations', 'calculations.id', '=', 'calculation_items.calculation_id')
            ->where('calculations.user_id', $user->getKey())
            ->whereNull('calculations.deleted_at')
            ->whereNotNull('calculation_items.material_id')
            ->select('calculation_items.material_id', DB::raw('COUNT(*) as uses'))
            ->groupBy('calculation_items.material_id')
            ->orderByDesc('uses')
            ->limit(6)
            ->pluck('uses', 'calculation_items.material_id');

        if ($frequentIds->isEmpty()) {
            return Material::query()->ownedBy($user)->active()->with('unit')->orderByDesc('updated_at')->limit(6)->get();
        }

        return Material::query()->ownedBy($user)->with('unit')->whereIn('id', $frequentIds->keys())->get()
            ->sortByDesc(fn (Material $material) => $frequentIds[$material->getKey()] ?? 0)
            ->values();
    }
}
