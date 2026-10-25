<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Concerns\TrashesRecords;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Http\Requests\OpportunityRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Http\Resources\OpportunityResource;
use Modules\Crm\Http\Resources\OrderResource;
use Modules\Crm\Http\Resources\QuoteResource;
use Modules\Crm\Models\ActivityLog;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Order;
use Modules\Crm\Models\Quote;
use Modules\Crm\Services\ActivityRecorder;
use Modules\Crm\Support\CrmOptions;
use Modules\Pricing\Http\Resources\CalculationResource;
use Modules\Pricing\Models\Calculation;

class OpportunityController extends Controller
{
    use TrashesRecords;

    public function __construct(private readonly ActivityRecorder $activities) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'stage' => in_array($request->query('stage'), CrmOptions::stageValues(), true)
                ? (string) $request->query('stage')
                : '',
            'status' => in_array($request->query('status'), [...CrmOptions::statusValues(), 'all', 'deleted'], true)
                ? (string) $request->query('status')
                : Opportunity::STATUS_OPEN,
            'customer_id' => $request->integer('customer_id') ?: null,
            'view' => $request->query('view') === 'board' ? 'board' : 'list',
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];

        $query = Opportunity::query()
            ->when($filters['status'] === 'deleted', fn ($q) => $q->onlyTrashed())
            ->ownedBy($user)
            // Кошчето може да съдържа запитване, чийто клиент също е изтрит.
            ->with(['customer' => fn ($relation) => $relation->withTrashed()])
            ->search($filters['search'])
            ->when($filters['stage'] !== '', fn ($q) => $q->where('stage', $filters['stage']))
            ->when(! in_array($filters['status'], ['all', 'deleted'], true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('customer_id', $id))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('event_date', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('event_date', '<=', $to));

        // Kanban-изгледът показва всички отворени възможности наведнъж;
        // списъкът е с пагинация, за да не тегли целия архив.
        $opportunities = $filters['view'] === 'board'
            ? $query->orderByRaw('event_date is null, event_date')->limit(200)->get()
            : $query->orderByRaw('event_date is null, event_date')->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('crm::opportunities/index', [
            'opportunities' => OpportunityResource::collection($opportunities),
            'filters' => $filters,
            'options' => CrmOptions::forForms(),
            'customers' => CustomerResource::collection(
                Customer::query()->ownedBy($user)->active()->orderBy('name')->limit(50)->get()
            ),
        ]);
    }

    public function show(Request $request, Opportunity $opportunity): Response
    {
        $this->authorize('view', $opportunity);

        $opportunity->load(['customer' => fn ($relation) => $relation->withTrashed(), 'activities']);

        $calculations = Calculation::query()
            ->ownedBy($request->user())
            ->where('crm_opportunity_id', $opportunity->getKey())
            ->orderByDesc('id')
            ->get();

        $quotes = Quote::query()
            ->ownedBy($request->user())
            ->where('crm_opportunity_id', $opportunity->getKey())
            ->orderByDesc('id')
            ->get();

        $orders = Order::query()
            ->ownedBy($request->user())
            ->where('crm_opportunity_id', $opportunity->getKey())
            ->orderByDesc('id')
            ->get();

        return Inertia::render('crm::opportunities/show', [
            'opportunity' => (new OpportunityResource($opportunity))->withDeletionInfo(),
            'customer' => new CustomerResource($opportunity->customer),
            'calculations' => CalculationResource::collection($calculations),
            'quotes' => QuoteResource::collection($quotes),
            'orders' => OrderResource::collection($orders),
            'options' => CrmOptions::forForms(),
        ]);
    }

    public function store(OpportunityRequest $request): RedirectResponse
    {
        $user = $request->user();

        $opportunity = new Opportunity($request->validated());
        $opportunity->user_id = $user->getKey();
        $opportunity->save();

        $this->activities->log(
            $user,
            ActivityLog::TYPE_OPPORTUNITY_CREATED,
            'Създадено запитване „'.$opportunity->title.'“ на етап „'.$opportunity->stageLabel().'“.',
            $opportunity,
        );

        return redirect()
            ->route('crm.opportunities.show', $opportunity)
            ->with('success', 'Запитването е създадено.');
    }

    public function update(OpportunityRequest $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);

        $previousStage = $opportunity->stage;
        $opportunity->update($request->validated());

        if ($opportunity->stage !== $previousStage) {
            $this->logStageChange($request, $opportunity, $previousStage);
        }

        return back()->with('success', 'Запитването е обновено.');
    }

    /** Бърза смяна само на етапа — от таблицата или от Kanban дъската. */
    public function stage(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);

        $validated = $request->validate([
            'stage' => ['required', Rule::in(CrmOptions::stageValues())],
        ]);

        if (! $opportunity->isOpen()) {
            return back()->withErrors(['stage' => 'Приключено запитване не сменя етап. Върнете го в „Отворена“, ако работата продължава.']);
        }

        $previousStage = $opportunity->stage;

        if ($previousStage === $validated['stage']) {
            return back();
        }

        $opportunity->update(['stage' => $validated['stage']]);
        $this->logStageChange($request, $opportunity, $previousStage);

        return back()->with('success', 'Етапът е променен на „'.$opportunity->stageLabel().'“.');
    }

    /** Приключване: спечелена или загубена; или връщане в работа. */
    public function close(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);

        $validated = $request->validate([
            'status' => ['required', Rule::in(CrmOptions::statusValues())],
            'lost_reason' => ['nullable', Rule::in(CrmOptions::lostReasonValues())],
            'lost_reason_note' => ['nullable', 'string', 'max:255'],
        ]);

        $previousStatus = $opportunity->status;

        if ($previousStatus === $validated['status']) {
            return back();
        }

        $opportunity->fill([
            'status' => $validated['status'],
            'lost_reason' => $validated['status'] === Opportunity::STATUS_LOST ? ($validated['lost_reason'] ?? null) : null,
            'lost_reason_note' => $validated['status'] === Opportunity::STATUS_LOST ? ($validated['lost_reason_note'] ?? null) : null,
        ]);

        $opportunity->closed_at = $validated['status'] === Opportunity::STATUS_OPEN ? null : Carbon::now();
        $opportunity->save();

        $this->activities->log(
            $request->user(),
            ActivityLog::TYPE_STATUS_CHANGED,
            'Статусът е променен от „'.(CrmOptions::STATUSES[$previousStatus] ?? $previousStatus).'“ на „'.$opportunity->statusLabel().'“'
                .($opportunity->lostReasonLabel() !== null ? ' (причина: '.$opportunity->lostReasonLabel().')' : '').'.',
            $opportunity,
            meta: ['from' => $previousStatus, 'to' => $opportunity->status],
        );

        return back()->with('success', 'Статусът е променен на „'.$opportunity->statusLabel().'“.');
    }

    /** Ръчна бележка в хронологията — винаги се маркира като ръчна. */
    public function note(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:500'],
        ]);

        $this->activities->log(
            $request->user(),
            ActivityLog::TYPE_NOTE,
            $validated['description'],
            $opportunity,
            automatic: false,
        );

        return back()->with('success', 'Бележката е добавена.');
    }

    /**
     * Премества запитването в Кошчето.
     *
     * Офертите, поръчките, калкулациите и задачите по него трябва да са
     * изтрити първи — иначе биха останали да сочат към нищо.
     */
    public function destroy(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('delete', $opportunity);

        if ($error = $this->moveToTrash($opportunity)) {
            return back()->withErrors(['delete' => $error]);
        }

        return redirect()
            ->route('crm.opportunities.index')
            ->with('success', 'Запитване „'.$opportunity->title.'“ е преместено в Кошчето.');
    }

    public function restore(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('restore', $opportunity);

        if ($error = $this->restoreFromTrash($opportunity)) {
            return back()->withErrors(['delete' => $error]);
        }

        return back()->with('success', 'Запитване „'.$opportunity->title.'“ е върнато от Кошчето.');
    }

    public function forceDestroy(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('forceDelete', $opportunity);

        $title = $opportunity->title;

        if ($error = $this->deleteForever($opportunity)) {
            return back()->withErrors(['delete' => $error]);
        }

        return redirect()
            ->route('crm.opportunities.index', ['status' => 'deleted'])
            ->with('success', 'Запитване „'.$title.'“ е изтрито окончателно.');
    }

    private function logStageChange(Request $request, Opportunity $opportunity, string $previousStage): void
    {
        $this->activities->log(
            $request->user(),
            ActivityLog::TYPE_STAGE_CHANGED,
            'Етапът е променен от „'.(CrmOptions::STAGES[$previousStage] ?? $previousStage).'“ на „'.$opportunity->stageLabel().'“.',
            $opportunity,
            meta: ['from' => $previousStage, 'to' => $opportunity->stage],
        );
    }
}
