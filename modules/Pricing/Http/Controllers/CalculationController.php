<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Controllers;

use App\Http\Controllers\Concerns\TrashesRecords;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalog\Http\Resources\MaterialResource;
use Modules\Catalog\Http\Resources\UnitResource;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;
use Modules\Core\Models\UserSetting;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Http\Resources\OpportunityResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Services\CalculationLinker;
use Modules\Crm\Support\CrmOptions;
use Modules\Pricing\Http\Requests\StoreCalculationRequest;
use Modules\Pricing\Http\Resources\CalculationResource;
use Modules\Pricing\Models\Calculation;
use Modules\Pricing\Services\CalculationRecorder;
use Modules\Pricing\Services\InvalidCalculationException;
use Modules\Pricing\Services\PricingCalculator;

class CalculationController extends Controller
{
    use TrashesRecords;

    /** История на калкулациите. */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'sort' => in_array($request->query('sort'), ['calculated_at', 'final_price', 'net_profit', 'service_name'], true)
                ? (string) $request->query('sort')
                : 'calculated_at',
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
            'status' => $request->query('status') === 'deleted' ? 'deleted' : 'active',
            'customer_id' => $request->integer('customer_id') ?: null,
        ];

        $calculations = Calculation::query()
            ->when($filters['status'] === 'deleted', fn ($q) => $q->onlyTrashed())
            ->ownedBy($user)
            ->withCount('items')
            ->with([
                'customer' => fn ($relation) => $relation->withTrashed(),
                'opportunity' => fn ($relation) => $relation->withTrashed(),
            ])
            ->search($filters['search'])
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('calculated_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('calculated_at', '<=', $to))
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('customer_id', $id))
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('pricing::calculations/index', [
            'calculations' => CalculationResource::collection($calculations),
            'trashed_count' => Calculation::query()->ownedBy($user)->onlyTrashed()->count(),
            'filters' => $filters,
        ]);
    }

    /** Екранът „Нова калкулация“. */
    public function create(Request $request): Response
    {
        $props = $this->formProps($request);

        // Отваряне от запитване („Нова калкулация“ в CRM) — клиентът и
        // запитването идват предварително попълнени.
        $opportunity = $request->integer('opportunity')
            ? Opportunity::query()->ownedBy($request->user())->with('customer')->find($request->integer('opportunity'))
            : null;

        return Inertia::render('pricing::calculations/create', [
            ...$props,
            'presetOpportunity' => $opportunity === null ? null : new OpportunityResource($opportunity),
            'presetCustomer' => $opportunity === null ? null : new CustomerResource($opportunity->customer),
        ]);
    }

    /** Дублиране: нова калкулация, предварително попълнена от стара. */
    public function duplicate(Request $request, Calculation $calculation): Response
    {
        $this->authorize('view', $calculation);

        $calculation->load(['items', 'customer', 'opportunity']);

        return Inertia::render('pricing::calculations/create', [
            ...$this->formProps($request),
            'duplicateOf' => new CalculationResource($calculation),
            'presetOpportunity' => $calculation->opportunity === null ? null : new OpportunityResource($calculation->opportunity),
            'presetCustomer' => $calculation->customer === null ? null : new CustomerResource($calculation->customer),
        ]);
    }

    public function store(StoreCalculationRequest $request, CalculationRecorder $recorder, CalculationLinker $linker): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Защита срещу двойно записване при многократно натискане на бутона.
        $idempotencyKey = $validated['idempotency_key'] ?? null;
        $cacheKey = $idempotencyKey !== null
            ? 'calculation-idempotency:'.$user->getKey().':'.$idempotencyKey
            : null;

        if ($cacheKey !== null && ($existingId = Cache::get($cacheKey)) !== null) {
            return redirect()
                ->route('calculations.show', $existingId)
                ->with('info', 'Калкулацията вече е записана.');
        }

        try {
            $result = PricingCalculator::withCurrentRates()->calculate($request->toCalculationInput());
        } catch (InvalidCalculationException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        // Записът и свързването с CRM са една атомарна операция — няма как да
        // се появи калкулация без връзка или запитване без калкулация.
        [$calculation, $link] = DB::transaction(function () use ($recorder, $linker, $user, $validated, $result): array {
            $calculation = $recorder->record($user, $validated, $result);

            return [$calculation, $linker->link($user, $calculation, $validated)];
        });

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $calculation->getKey(), now()->addMinutes(30));
        }

        return redirect()
            ->route('calculations.show', $calculation)
            ->with('success', $this->savedMessage($calculation, $link));
    }

    /**
     * Съобщението след запис казва точно какво се е случило в CRM —
     * без да се твърди нещо, което системата не е направила.
     *
     * @param  array{customer: ?object, opportunity: ?object, opportunity_was_created: bool}  $link
     */
    private function savedMessage(Calculation $calculation, array $link): string
    {
        $base = 'Калкулация '.$calculation->calculation_number.' е записана';

        if ($link['opportunity'] === null) {
            return $link['customer'] === null
                ? $base.'.'
                : $base.' за клиент „'.$link['customer']->name.'“.';
        }

        return $link['opportunity_was_created']
            ? $base.' и е създадено ново запитване „'.$link['opportunity']->title.'“.'
            : $base.' и е свързана със запитване „'.$link['opportunity']->title.'“.';
    }

    public function show(Request $request, Calculation $calculation): Response
    {
        $this->authorize('view', $calculation);

        $calculation->load([
            'items',
            'customer' => fn ($relation) => $relation->withTrashed(),
            'opportunity' => fn ($relation) => $relation->withTrashed(),
        ]);

        return Inertia::render('pricing::calculations/show', [
            'calculation' => (new CalculationResource($calculation))->withDeletionInfo(),
        ]);
    }

    /** Компактна версия за печат / експорт в PDF през браузъра. */
    public function print(Request $request, Calculation $calculation): Response
    {
        $this->authorize('view', $calculation);

        $calculation->load('items');

        return Inertia::render('pricing::calculations/print', [
            'calculation' => new CalculationResource($calculation),
            'company' => ['name' => config('app.name')],
        ]);
    }

    /** Премества калкулацията в Кошчето. */
    public function destroy(Request $request, Calculation $calculation): RedirectResponse
    {
        $this->authorize('delete', $calculation);

        $calculation->delete();

        return redirect()
            ->route('calculations.index')
            ->with('success', 'Калкулация '.$calculation->calculation_number.' е преместена в Кошчето.');
    }

    public function restore(Request $request, Calculation $calculation): RedirectResponse
    {
        $this->authorize('restore', $calculation);

        if ($error = $this->restoreFromTrash($calculation)) {
            return back()->withErrors(['delete' => $error]);
        }

        return back()->with('success', 'Калкулация '.$calculation->calculation_number.' е върната от Кошчето.');
    }

    public function forceDestroy(Request $request, Calculation $calculation): RedirectResponse
    {
        $this->authorize('forceDelete', $calculation);

        $number = $calculation->calculation_number;
        $calculation->forceDelete();

        return redirect()
            ->route('calculations.index', ['status' => 'deleted'])
            ->with('success', 'Калкулация '.$number.' е изтрита окончателно.');
    }

    /** Общите props за екрана за въвеждане. */
    private function formProps(Request $request): array
    {
        $user = $request->user();
        $settings = UserSetting::forUser($user);
        $calculator = PricingCalculator::withCurrentRates();

        $units = Unit::query()->ownedBy($user)->active()->orderBy('name')->get();

        // Зарежда само най-често използваните материали; останалите се търсят
        // чрез autocomplete, за да не се тегли целият каталог на мобилен телефон.
        $materials = Material::query()
            ->ownedBy($user)
            ->active()
            ->with('unit')
            ->orderBy('name')
            ->limit(50)
            ->get();

        return [
            'units' => UnitResource::collection($units),
            'materials' => MaterialResource::collection($materials),
            'customers' => CustomerResource::collection(
                Customer::query()->ownedBy($user)->active()->orderBy('name')->limit(50)->get()
            ),
            'crmOptions' => CrmOptions::forForms(),
            'defaults' => [
                'net_margin_percent' => (float) $settings->default_net_margin * 100,
                'rounding_strategy' => $settings->default_rounding_strategy,
                'calculated_at' => Carbon::today()->toDateString(),
                'default_unit_id' => $settings->default_unit_id,
            ],
            'taxes' => [
                'corporate' => (string) $calculator->taxes()->corporate,
                'dividend' => (string) $calculator->taxes()->dividend,
                'vat' => (string) $calculator->taxes()->vat,
                'net_factor' => (string) $calculator->taxes()->netFactor(),
                'max_net_margin' => $calculator->maximumNetMargin(),
            ],
        ];
    }
}
