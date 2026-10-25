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
use Modules\Core\Models\UserSetting;
use Modules\Crm\Http\Requests\QuoteRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Http\Resources\OpportunityResource;
use Modules\Crm\Http\Resources\QuoteResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Quote;
use Modules\Crm\Services\QuoteWriter;
use Modules\Crm\Support\CrmOptions;
use Modules\Pricing\Http\Resources\CalculationResource;
use Modules\Pricing\Models\Calculation;

class QuoteController extends Controller
{
    use TrashesRecords;

    public function __construct(private readonly QuoteWriter $writer) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => in_array($request->query('status'), [...CrmOptions::quoteStatusValues(), 'all', 'deleted'], true)
                ? (string) $request->query('status')
                : 'all',
            'customer_id' => $request->integer('customer_id') ?: null,
        ];

        $quotes = Quote::query()
            ->when($filters['status'] === 'deleted', fn ($q) => $q->onlyTrashed())
            ->ownedBy($user)
            ->with(['opportunity' => fn ($relation) => $relation->withTrashed()])
            ->search($filters['search'])
            ->when(! in_array($filters['status'], ['all', 'deleted'], true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('customer_id', $id))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('crm::quotes/index', [
            'quotes' => QuoteResource::collection($quotes),
            'filters' => $filters,
            'options' => CrmOptions::forForms(),
        ]);
    }

    /** Екран за нова оферта — по избор предварително попълнена от запитване. */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $settings = UserSetting::forUser($user);

        $opportunity = $request->integer('opportunity')
            ? Opportunity::query()->ownedBy($user)->with('customer')->find($request->integer('opportunity'))
            : null;

        $customer = $opportunity?->customer ?? ($request->integer('customer')
            ? Customer::query()->ownedBy($user)->find($request->integer('customer'))
            : null);

        // Калкулациите на запитването са най-честият източник за редовете.
        $calculations = $opportunity !== null
            ? Calculation::query()->ownedBy($user)->where('crm_opportunity_id', $opportunity->getKey())->orderByDesc('id')->get()
            : ($customer !== null
                ? Calculation::query()->ownedBy($user)->where('customer_id', $customer->getKey())->orderByDesc('id')->limit(20)->get()
                : collect());

        return Inertia::render('crm::quotes/form', [
            'quote' => null,
            'presetCustomer' => $customer === null ? null : new CustomerResource($customer),
            'presetOpportunity' => $opportunity === null ? null : new OpportunityResource($opportunity),
            'calculations' => CalculationResource::collection($calculations),
            'customers' => CustomerResource::collection(
                Customer::query()->ownedBy($user)->active()->orderBy('name')->limit(50)->get()
            ),
            'defaults' => [
                'issued_at' => Carbon::today()->toDateString(),
                'valid_until' => Carbon::today()->addDays((int) $settings->default_quote_validity_days)->toDateString(),
                'terms' => $settings->default_quote_terms,
            ],
            'options' => CrmOptions::forForms(),
        ]);
    }

    public function edit(Request $request, Quote $quote): Response
    {
        $this->authorize('update', $quote);

        $quote->load(['items', 'opportunity', 'customer']);

        $calculations = Calculation::query()
            ->ownedBy($request->user())
            ->where(function ($query) use ($quote): void {
                $query->where('customer_id', $quote->customer_id);

                if ($quote->crm_opportunity_id !== null) {
                    $query->orWhere('crm_opportunity_id', $quote->crm_opportunity_id);
                }
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return Inertia::render('crm::quotes/form', [
            'quote' => (new QuoteResource($quote))->withInternalNotes(),
            'presetCustomer' => new CustomerResource($quote->customer),
            'presetOpportunity' => $quote->opportunity === null ? null : new OpportunityResource($quote->opportunity),
            'calculations' => CalculationResource::collection($calculations),
            'customers' => CustomerResource::collection(collect([$quote->customer])),
            'defaults' => null,
            'options' => CrmOptions::forForms(),
        ]);
    }

    public function show(Request $request, Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $quote->load(['items', 'opportunity', 'versions']);

        return Inertia::render('crm::quotes/show', [
            'quote' => (new QuoteResource($quote))->withInternalNotes()->withDeletionInfo(),
            'options' => CrmOptions::forForms(),
        ]);
    }

    /**
     * Клиентският изглед за печат / запис като PDF.
     *
     * Този екран НЕ променя статуса на офертата — генерирането на документ и
     * реалното му изпращане са различни действия.
     */
    public function print(Request $request, Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $quote->load('items');

        return Inertia::render('crm::quotes/print', [
            'quote' => new QuoteResource($quote),
            'company' => UserSetting::forUser($request->user())->companyDetails(),
        ]);
    }

    public function store(QuoteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $customer = Customer::query()->ownedBy($user)->findOrFail((int) $validated['customer_id']);
        $quote = $this->writer->create($user, $customer, $validated);

        return redirect()
            ->route('crm.quotes.show', $quote)
            ->with('success', 'Оферта '.$quote->quote_number.' е създадена като чернова.');
    }

    public function update(QuoteRequest $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        if (! $quote->isEditable()) {
            return back()->withErrors(['status' => 'Приета оферта не може да се променя. Създайте нова оферта.']);
        }

        $wasSent = $quote->wasSent();
        $quote = $this->writer->update($request->user(), $quote, $request->validated());

        return redirect()
            ->route('crm.quotes.show', $quote)
            ->with(
                'success',
                $wasSent
                    ? 'Създадена е версия '.$quote->version.'. Изпратената версия остава непроменена.'
                    : 'Офертата е обновена.',
            );
    }

    /** Смяна на статуса. „Изпратена“ изисква изрично потвърждение. */
    public function status(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate([
            'status' => ['required', Rule::in(CrmOptions::quoteStatusValues())],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if ($validated['status'] === Quote::STATUS_SENT) {
            $this->writer->markSent($user, $quote);

            return back()->with('success', 'Офертата е отбелязана като изпратена.');
        }

        if (in_array($validated['status'], [Quote::STATUS_ACCEPTED, Quote::STATUS_DECLINED], true)) {
            if (! $quote->wasSent()) {
                return back()->withErrors([
                    'status' => 'Отбележете офертата като изпратена, преди да запишете отговор от клиента.',
                ]);
            }

            $this->writer->recordReply($user, $quote, $validated['status'], $validated['note'] ?? null);

            return back()->with('success', 'Отговорът на клиента е записан.');
        }

        $quote->status = $validated['status'];
        $quote->save();

        return back()->with('success', 'Статусът е променен на „'.$quote->statusLabel().'“.');
    }

    /**
     * Премества офертата в Кошчето.
     *
     * Изпратена оферта също може да бъде изтрита — грешките се случват —
     * но остава в Кошчето заедно със замразените си версии и може да бъде
     * върната. Поръчка или задача по нея трябва да бъдат изтрити първи.
     */
    public function destroy(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        if ($error = $this->moveToTrash($quote)) {
            return back()->withErrors(['delete' => $error]);
        }

        return redirect()
            ->route('crm.quotes.index')
            ->with('success', 'Оферта '.$quote->quote_number.' е преместена в Кошчето.');
    }

    public function restore(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('restore', $quote);

        if ($error = $this->restoreFromTrash($quote)) {
            return back()->withErrors(['delete' => $error]);
        }

        return back()->with('success', 'Оферта '.$quote->quote_number.' е върната от Кошчето.');
    }

    public function forceDestroy(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('forceDelete', $quote);

        $number = $quote->quote_number;

        if ($error = $this->deleteForever($quote)) {
            return back()->withErrors(['delete' => $error]);
        }

        return redirect()
            ->route('crm.quotes.index', ['status' => 'deleted'])
            ->with('success', 'Оферта '.$number.' е изтрита окончателно.');
    }
}
