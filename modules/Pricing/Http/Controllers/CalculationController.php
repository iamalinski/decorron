<?php

declare(strict_types=1);

namespace Modules\Pricing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalog\Http\Resources\MaterialResource;
use Modules\Catalog\Http\Resources\UnitResource;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;
use Modules\Core\Models\UserSetting;
use Modules\Pricing\Http\Requests\StoreCalculationRequest;
use Modules\Pricing\Http\Resources\CalculationResource;
use Modules\Pricing\Models\Calculation;
use Modules\Pricing\Services\CalculationRecorder;
use Modules\Pricing\Services\InvalidCalculationException;
use Modules\Pricing\Services\PricingCalculator;

class CalculationController extends Controller
{
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
        ];

        $calculations = Calculation::query()
            ->ownedBy($user)
            ->withCount('items')
            ->search($filters['search'])
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('calculated_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('calculated_at', '<=', $to))
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('pricing::calculations/index', [
            'calculations' => CalculationResource::collection($calculations),
            'filters' => $filters,
        ]);
    }

    /** Екранът „Нова калкулация“. */
    public function create(Request $request): Response
    {
        return Inertia::render('pricing::calculations/create', $this->formProps($request));
    }

    /** Дублиране: нова калкулация, предварително попълнена от стара. */
    public function duplicate(Request $request, Calculation $calculation): Response
    {
        $this->authorize('view', $calculation);

        $calculation->load('items');

        return Inertia::render('pricing::calculations/create', [
            ...$this->formProps($request),
            'duplicateOf' => new CalculationResource($calculation),
        ]);
    }

    public function store(StoreCalculationRequest $request, CalculationRecorder $recorder): RedirectResponse
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

        $calculation = $recorder->record($user, $validated, $result);

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $calculation->getKey(), now()->addMinutes(30));
        }

        return redirect()
            ->route('calculations.show', $calculation)
            ->with('success', 'Калкулация '.$calculation->calculation_number.' е записана.');
    }

    public function show(Request $request, Calculation $calculation): Response
    {
        $this->authorize('view', $calculation);

        $calculation->load('items');

        return Inertia::render('pricing::calculations/show', [
            'calculation' => new CalculationResource($calculation),
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

    public function destroy(Request $request, Calculation $calculation): RedirectResponse
    {
        $this->authorize('delete', $calculation);

        $number = $calculation->calculation_number;
        $calculation->delete();

        return redirect()
            ->route('calculations.index')
            ->with('success', 'Калкулация '.$number.' е изтрита.');
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
