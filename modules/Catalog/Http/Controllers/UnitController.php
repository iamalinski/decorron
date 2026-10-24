<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalog\Http\Requests\UnitRequest;
use Modules\Catalog\Http\Resources\UnitResource;
use Modules\Catalog\Models\Unit;

class UnitController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $status = in_array($request->query('status'), ['active', 'archived', 'all'], true)
            ? (string) $request->query('status')
            : 'active';

        $units = Unit::query()
            ->ownedBy($user)
            ->withCount('materials')
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'archived', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->get();

        return Inertia::render('catalog::units/index', [
            'units' => UnitResource::collection($units),
            'filters' => ['status' => $status],
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $unit = new Unit($request->validated());
        $unit->user_id = $request->user()->getKey();
        $unit->save();

        return back()->with('success', 'Мерната единица „'.$unit->name.'“ е добавена.');
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $unit->update($request->validated());

        return back()->with('success', 'Мерната единица „'.$unit->name.'“ е обновена.');
    }

    /**
     * Архивиране вместо изтриване. Записаните калкулации пазят snapshot на
     * името и символа, затова остават четими и след архивиране.
     */
    public function archive(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $unit->update(['is_active' => ! $unit->is_active]);

        return back()->with(
            'success',
            $unit->is_active
                ? 'Мерната единица „'.$unit->name.'“ е активирана.'
                : 'Мерната единица „'.$unit->name.'“ е архивирана.',
        );
    }
}
