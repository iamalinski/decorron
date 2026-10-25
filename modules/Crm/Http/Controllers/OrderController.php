<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Concerns\TrashesRecords;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Http\Requests\OrderRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Http\Resources\OrderResource;
use Modules\Crm\Http\Resources\QuoteResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Order;
use Modules\Crm\Models\Quote;
use Modules\Crm\Services\OrderWriter;
use Modules\Crm\Support\CrmOptions;

class OrderController extends Controller
{
    use TrashesRecords;

    public function __construct(private readonly OrderWriter $writer) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => in_array($request->query('status'), [...CrmOptions::orderStatusValues(), 'all', 'active', 'deleted'], true)
                ? (string) $request->query('status')
                : 'active',
            'customer_id' => $request->integer('customer_id') ?: null,
        ];

        $orders = Order::query()
            ->when($filters['status'] === 'deleted', fn ($q) => $q->onlyTrashed())
            ->ownedBy($user)
            ->search($filters['search'])
            ->when($filters['status'] === 'active', fn ($q) => $q->active())
            ->when(! in_array($filters['status'], ['active', 'all', 'deleted'], true), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('customer_id', $id))
            ->orderByRaw('event_date is null, event_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('crm::orders/index', [
            'orders' => OrderResource::collection($orders),
            'filters' => $filters,
            'options' => CrmOptions::forForms(),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['items', 'opportunity', 'quote']);

        return Inertia::render('crm::orders/show', [
            'order' => (new OrderResource($order))->withDeletionInfo(),
            'quote' => $order->quote === null ? null : new QuoteResource($order->quote),
            'options' => CrmOptions::forForms(),
        ]);
    }

    /** Нова поръчка — по избор предварително попълнена от приета оферта. */
    public function create(Request $request): Response
    {
        $user = $request->user();

        $quote = $request->integer('quote')
            ? Quote::query()->ownedBy($user)->with(['items', 'customer'])->find($request->integer('quote'))
            : null;

        $customer = $quote?->customer ?? ($request->integer('customer')
            ? Customer::query()->ownedBy($user)->find($request->integer('customer'))
            : null);

        return Inertia::render('crm::orders/form', [
            'order' => null,
            'presetQuote' => $quote === null ? null : new QuoteResource($quote),
            'presetCustomer' => $customer === null ? null : new CustomerResource($customer),
            'customers' => CustomerResource::collection(
                Customer::query()->ownedBy($user)->active()->orderBy('name')->limit(50)->get()
            ),
            'options' => CrmOptions::forForms(),
        ]);
    }

    public function edit(Request $request, Order $order): Response
    {
        $this->authorize('update', $order);

        $order->load('items');

        return Inertia::render('crm::orders/form', [
            'order' => (new OrderResource($order))->withDeletionInfo(),
            'presetQuote' => null,
            'presetCustomer' => new CustomerResource($order->customer),
            'customers' => CustomerResource::collection(collect([$order->customer])),
            'options' => CrmOptions::forForms(),
        ]);
    }

    public function store(OrderRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $customer = Customer::query()->ownedBy($user)->findOrFail((int) $validated['customer_id']);
        $order = $this->writer->create($user, $customer, $validated);

        return redirect()
            ->route('crm.orders.show', $order)
            ->with('success', 'Поръчка '.$order->order_number.' е създадена.');
    }

    /**
     * Превръща приета оферта в поръчка.
     *
     * Повторното извикване връща вече създадената поръчка — без дубликати.
     */
    public function fromQuote(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        if ($quote->status !== Quote::STATUS_ACCEPTED) {
            return back()->withErrors(['status' => 'Поръчка се създава от приета оферта. Първо отбележете офертата като приета.']);
        }

        // Търси се и в Кошчето: една оферта може да има само една поръчка,
        // затова изтритата се връща, вместо да се прави втора.
        $existing = Order::query()->withTrashed()->ownedBy($request->user())->where('quote_id', $quote->getKey())->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();

                return redirect()
                    ->route('crm.orders.show', $existing)
                    ->with('info', 'Поръчка '.$existing->order_number.' за тази оферта беше в Кошчето и е върната.');
            }

            return redirect()
                ->route('crm.orders.show', $existing)
                ->with('info', 'За тази оферта вече има поръчка '.$existing->order_number.'.');
        }

        $order = $this->writer->createFromQuote($request->user(), $quote);

        return redirect()
            ->route('crm.orders.show', $order)
            ->with('success', 'Поръчка '.$order->order_number.' е създадена от оферта '.$quote->quote_number.'.');
    }

    public function update(OrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $order = $this->writer->update($request->user(), $order, $request->validated());

        return redirect()->route('crm.orders.show', $order)->with('success', 'Поръчката е обновена.');
    }

    public function status(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validate([
            'status' => ['required', Rule::in(CrmOptions::orderStatusValues())],
        ]);

        if ($order->status === $validated['status']) {
            return back();
        }

        $this->writer->changeStatus($request->user(), $order, $validated['status']);

        return back()->with('success', 'Статусът е променен на „'.$order->statusLabel().'“.');
    }

    /** Премества поръчката в Кошчето. */
    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        if ($error = $this->moveToTrash($order)) {
            return back()->withErrors(['delete' => $error]);
        }

        return redirect()
            ->route('crm.orders.index')
            ->with('success', 'Поръчка '.$order->order_number.' е преместена в Кошчето.');
    }

    public function restore(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('restore', $order);

        if ($error = $this->restoreFromTrash($order)) {
            return back()->withErrors(['delete' => $error]);
        }

        return back()->with('success', 'Поръчка '.$order->order_number.' е върната от Кошчето.');
    }

    public function forceDestroy(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('forceDelete', $order);

        $number = $order->order_number;

        if ($error = $this->deleteForever($order)) {
            return back()->withErrors(['delete' => $error]);
        }

        return redirect()
            ->route('crm.orders.index', ['status' => 'deleted'])
            ->with('success', 'Поръчка '.$number.' е изтрита окончателно.');
    }
}
