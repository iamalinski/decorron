<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Http\Controllers\MaterialController;
use Modules\Catalog\Http\Controllers\UnitController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('materials', [MaterialController::class, 'index'])->name('materials.index');
    Route::get('materials/search', [MaterialController::class, 'search'])->name('materials.search');
    Route::post('materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::patch('materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
    Route::patch('materials/{material}/archive', [MaterialController::class, 'archive'])->name('materials.archive');

    Route::get('units', [UnitController::class, 'index'])->name('units.index');
    Route::post('units', [UnitController::class, 'store'])->name('units.store');
    Route::patch('units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::patch('units/{unit}/archive', [UnitController::class, 'archive'])->name('units.archive');
});
