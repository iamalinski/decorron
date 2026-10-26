<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Concerns\TrashesRecords;
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
    use TrashesRecords;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => trim((string) $request->query('category', '')),
            'status' => in_array($request->query('status'), ['active', 'archived', 'all', 'deleted'], true)
                ? (string) $request->query('status')
                : 'active',
        ];

        $materials = Material::query()
            ->ownedBy($user)
            ->with('unit')
            // Броят на редовете решава дали изтриването ще е окончателно
            // или в Кошчето — показва се още в диалога за потвърждение.
            ->withCount('calculationItems')
            ->search($filters['search'])
            ->when($filters['category'] !== '', fn ($q) => $q->where('category', $filters['category']))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'archived', fn ($q) => $q->where('is_active', false))
            ->when($filters['status'] === 'deleted', fn ($q) => $q->onlyTrashed())
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
            'trashed_count' => Material::query()->ownedBy($user)->onlyTrashed()->count(),
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
     * Архивирането скрива материала от търсачките, без да го изтрива.
     *
     * Остава като отделно действие от изтриването: архивираният материал
     * още може да бъде избран наново, а изтритият — не.
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

    /**
     * Изтрива материала — окончателно, ако никоя калкулация не го ползва.
     *
     * Използваният материал отива в Кошчето, защото редовете на записаните
     * калкулации още сочат към него. Така каталогът се чисти от грешки, без
     * историята да губи връзката си с него.
     */
    public function destroy(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('delete', $material);

        $name = $material->name;
        $uses = $material->usageCount();

        if ($uses === 0) {
            $material->forceDelete();

            return back()->with('success', 'Материалът „'.$name.'“ е изтрит.');
        }

        $material->delete();

        return back()->with(
            'success',
            'Материалът „'.$name.'“ е преместен в Кошчето. Използва се в '
                .$uses.' '.($uses === 1 ? 'ред' : 'реда').' от записани калкулации, затова не се изтрива окончателно.',
        );
    }

    public function restore(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('restore', $material);

        $error = $this->restoreFromTrash($material);

        if ($error !== null) {
            return back()->withErrors(['delete' => $error]);
        }

        return back()->with('success', 'Материалът „'.$material->name.'“ е върнат в каталога.');
    }

    /**
     * Окончателно изтриване от Кошчето.
     *
     * Връзката на редовете към каталога отпада (material_id става NULL),
     * но самите редове запазват името, кода и цените си като snapshot.
     */
    public function forceDestroy(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('forceDelete', $material);

        $name = $material->name;
        $error = $this->deleteForever($material);

        if ($error !== null) {
            return back()->withErrors(['delete' => $error]);
        }

        return back()->with('success', 'Материалът „'.$name.'“ е изтрит окончателно.');
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
