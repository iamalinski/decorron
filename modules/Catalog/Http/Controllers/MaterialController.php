<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalog\Http\Requests\MaterialRequest;
use Modules\Catalog\Http\Resources\MaterialResource;
use Modules\Catalog\Http\Resources\UnitResource;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;

class MaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => trim((string) $request->query('category', '')),
            'status' => in_array($request->query('status'), ['active', 'archived', 'all'], true)
                ? (string) $request->query('status')
                : 'active',
        ];

        $materials = Material::query()
            ->ownedBy($user)
            ->with('unit')
            ->search($filters['search'])
            ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'archived', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('catalog::materials/index', [
            'materials' => MaterialResource::collection($materials),
            'units' => UnitResource::collection(Unit::query()->ownedBy($user)->active()->orderBy('name')->get()),
            'categories' => Material::query()->ownedBy($user)
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
            'filters' => $filters,
        ]);
    }

    public function store(MaterialRequest $request): RedirectResponse|JsonResponse
    {
        $material = new Material($request->validated());
        $material->user_id = $request->user()->getKey();
        $material->save();
        $material->load('unit');

        // Бързо добавяне от екрана за калкулация очаква JSON.
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['material' => (new MaterialResource($material))->resolve()], 201);
        }

        return back()->with('success', 'Материалът „'.$material->name.'“ е добавен.');
    }

    public function update(MaterialRequest $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $material->update($request->validated());

        return back()->with('success', 'Материалът „'.$material->name.'“ е обновен.');
    }

    /**
     * Материалите не се изтриват физически, за да не се повредят
     * историческите калкулации — вместо това се архивират.
     */
    public function archive(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $material->update(['is_active' => ! $material->is_active]);

        return back()->with(
            'success',
            $material->is_active
                ? 'Материалът „'.$material->name.'“ е върнат в активните.'
                : 'Материалът „'.$material->name.'“ е архивиран.',
        );
    }

    /** Autocomplete за екрана за калкулация. */
    public function search(Request $request): JsonResponse
    {
        $materials = Material::query()
            ->ownedBy($request->user())
            ->active()
            ->with('unit')
            ->search((string) $request->query('q', ''))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['materials' => MaterialResource::collection($materials)->resolve()]);
    }
}
