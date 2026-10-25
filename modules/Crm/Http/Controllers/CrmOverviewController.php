<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Http\Resources\OpportunityResource;
use Modules\Crm\Http\Resources\OrderResource;
use Modules\Crm\Http\Resources\QuoteResource;
use Modules\Crm\Http\Resources\TaskResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Order;
use Modules\Crm\Models\Quote;
use Modules\Crm\Models\Task;
use Modules\Crm\Support\CrmOptions;

/**
 * Обзор на CRM — състоянието на фунията и какво изисква внимание.
 */
class CrmOverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $stageCounts = Opportunity::query()
            ->ownedBy($user)
            ->open()
            ->select('stage', DB::raw('count(*) as total'))
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $sourceCounts = Opportunity::query()
            ->ownedBy($user)
            ->whereNotNull('source')
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->pluck('total', 'source');

        return Inertia::render('crm::overview', [
            'stats' => [
                'customers' => Customer::query()->ownedBy($user)->active()->count(),
                'open_opportunities' => Opportunity::query()->ownedBy($user)->open()->count(),
                'won_this_month' => Opportunity::query()->ownedBy($user)
                    ->where('status', Opportunity::STATUS_WON)->where('closed_at', '>=', $monthStart)->count(),
                'lost_this_month' => Opportunity::query()->ownedBy($user)
                    ->where('status', Opportunity::STATUS_LOST)->where('closed_at', '>=', $monthStart)->count(),
                'quotes_awaiting' => Quote::query()->ownedBy($user)->awaitingReply()->count(),
                'active_orders' => Order::query()->ownedBy($user)->active()->count(),
                'overdue_tasks' => Task::query()->ownedBy($user)->overdue()->count(),
            ],

            'pipeline' => array_map(
                static fn (string $stage, string $label): array => [
                    'stage' => $stage,
                    'label' => $label,
                    'count' => (int) ($stageCounts[$stage] ?? 0),
                ],
                array_keys(CrmOptions::STAGES),
                array_values(CrmOptions::STAGES),
            ),

            'sources' => $sourceCounts->map(fn (int $total, string $source): array => [
                'source' => $source,
                'label' => CrmOptions::SOURCES[$source] ?? $source,
                'count' => $total,
            ])->values(),

            'recentOpportunities' => OpportunityResource::collection(
                Opportunity::query()->ownedBy($user)->with('customer')->open()->orderByDesc('id')->limit(6)->get()
            ),
            'quotesAwaiting' => QuoteResource::collection(
                Quote::query()->ownedBy($user)->awaitingReply()->orderByRaw('valid_until is null, valid_until')->limit(6)->get()
            ),
            'upcomingOrders' => OrderResource::collection(
                Order::query()->ownedBy($user)->active()
                    ->whereNotNull('event_date')
                    ->whereDate('event_date', '>=', $today->toDateString())
                    ->orderBy('event_date')->limit(6)->get()
            ),
            'overdueTasks' => TaskResource::collection(
                Task::query()->ownedBy($user)->with('customer')->overdue()->orderBy('due_on')->limit(6)->get()
            ),
        ]);
    }
}
