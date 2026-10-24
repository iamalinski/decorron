<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Catalog\Http\Resources\MaterialResource;
use Modules\Catalog\Models\Material;
use Modules\Pricing\Http\Resources\CalculationResource;
use Modules\Pricing\Models\Calculation;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $recent = Calculation::query()
            ->ownedBy($user)
            ->withCount('items')
            ->orderByDesc('calculated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // Най-често използваните материали, изведени от реалните редове в
        // калкулациите — без фиктивни статистики.
        $frequentMaterialIds = DB::table('calculation_items')
            ->join('calculations', 'calculations.id', '=', 'calculation_items.calculation_id')
            ->where('calculations.user_id', $user->getKey())
            ->whereNotNull('calculation_items.material_id')
            ->select('calculation_items.material_id', DB::raw('COUNT(*) as uses'))
            ->groupBy('calculation_items.material_id')
            ->orderByDesc('uses')
            ->limit(6)
            ->pluck('uses', 'calculation_items.material_id');

        $frequentMaterials = $frequentMaterialIds->isEmpty()
            ? Material::query()->ownedBy($user)->active()->with('unit')->orderByDesc('updated_at')->limit(6)->get()
            : Material::query()->ownedBy($user)->with('unit')->whereIn('id', $frequentMaterialIds->keys())->get()
                ->sortByDesc(fn (Material $m) => $frequentMaterialIds[$m->getKey()] ?? 0)
                ->values();

        return Inertia::render('core::dashboard', [
            'stats' => [
                'calculations_count' => Calculation::query()->ownedBy($user)->count(),
                'materials_count' => Material::query()->ownedBy($user)->active()->count(),
                'last_calculated_at' => $recent->first()?->calculated_at?->toDateString(),
            ],
            'recentCalculations' => CalculationResource::collection($recent),
            'frequentMaterials' => MaterialResource::collection($frequentMaterials),
        ]);
    }
}
