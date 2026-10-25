<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Concerns\TrashesRecords;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Http\Requests\CustomerRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Http\Resources\OpportunityResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Support\CrmOptions;

class CustomerController extends Controller
{
    use TrashesRecords;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => in_array($request->query('status'), ['active', 'archived', 'all', 'deleted'], true)
                ? (string) $request->query('status')
                : 'active',
            'source' => in_array($request->query('source'), CrmOptions::sourceValues(), true)
                ? (string) $request->query('source')
                : '',
            'sort' => in_array($request->query('sort'), ['name', 'created_at'], true)
                ? (string) $request->query('sort')
                : 'name',
            'direction' => $request->query('direction') === 'desc' ? 'desc' : 'asc',
        ];

        $customers = Customer::query()
            ->when($filters['status'] === 'deleted', fn ($q) => $q->onlyTrashed())
            ->ownedBy($user)
            ->search($filters['search'])
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_archived', false))
            ->when($filters['status'] === 'archived', fn ($q) => $q->where('is_archived', true))
            ->when($filters['source'] !== '', fn ($q) => $q->where('source', $filters['source']))
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('crm::customers/index', [
            'customers' => CustomerResource::collection($customers),
            'filters' => $filters,
            'options' => CrmOptions::forForms(),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $opportunities = Opportunity::query()
            ->ownedBy($request->user())
            ->where('customer_id', $customer->getKey())
            ->with('customer')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('crm::customers/show', [
            'customer' => (new CustomerResource($customer))->withDeletionInfo(),
            'opportunities' => OpportunityResource::collection($opportunities),
            'options' => CrmOptions::forForms(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = new Customer($request->customerAttributes());
        $customer->user_id = $request->user()->getKey();
        $customer->save();

        // Бързо добавяне от друг екран (калкулация, ново запитване) очаква JSON,
        // за да не се губи попълненото дотук.
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['customer' => (new CustomerResource($customer))->resolve()], 201);
        }

        return redirect()
            ->route('crm.customers.show', $customer)
            ->with('success', 'Клиентът „'.$customer->name.'“ е добавен.');
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update($request->customerAttributes());

        return back()->with('success', 'Данните на „'.$customer->name.'“ са обновени.');
    }

    /** Архивиране вместо изтриване — историята остава четима. */
    public function archive(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update(['is_archived' => ! $customer->is_archived]);

        return back()->with(
            'success',
            $customer->is_archived
                ? 'Клиентът „'.$customer->name.'“ е архивиран.'
                : 'Клиентът „'.$customer->name.'“ е върнат в активните.',
        );
    }

    /** Отворените запитвания на клиента — за свързване от екрана за калкулация. */
    public function opportunities(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $opportunities = Opportunity::query()
            ->ownedBy($request->user())
            ->where('customer_id', $customer->getKey())
            ->open()
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'opportunities' => OpportunityResource::collection($opportunities)->resolve(),
        ]);
    }

    /**
     * Премества клиента в Кошчето.
     *
     * Клиентът е последното звено във веригата: докато към него сочат
     * активни запитвания, оферти, поръчки, калкулации или задачи, той не
     * се изтрива. Съобщението казва точно какво пречи.
     */
    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        if ($error = $this->moveToTrash($customer)) {
            return back()->withErrors(['delete' => $error]);
        }

        return redirect()
            ->route('crm.customers.index')
            ->with('success', 'Клиентът „'.$customer->name.'“ е преместен в Кошчето.');
    }

    /** Връщане от Кошчето. */
    public function restore(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('restore', $customer);

        if ($error = $this->restoreFromTrash($customer)) {
            return back()->withErrors(['delete' => $error]);
        }

        return back()->with('success', 'Клиентът „'.$customer->name.'“ е върнат от Кошчето.');
    }

    /** Окончателно изтриване от базата — без връщане назад. */
    public function forceDestroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('forceDelete', $customer);

        $name = $customer->name;

        if ($error = $this->deleteForever($customer)) {
            return back()->withErrors(['delete' => $error]);
        }

        return redirect()
            ->route('crm.customers.index', ['status' => 'deleted'])
            ->with('success', 'Клиентът „'.$name.'“ е изтрит окончателно.');
    }

    /** Autocomplete при избор на клиент в други екрани. */
    public function search(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->ownedBy($request->user())
            ->active()
            ->search((string) $request->query('q', ''))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['customers' => CustomerResource::collection($customers)->resolve()]);
    }
}
