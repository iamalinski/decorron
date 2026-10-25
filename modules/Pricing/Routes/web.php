<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Pricing\Http\Controllers\CalculationController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('calculations', [CalculationController::class, 'index'])->name('calculations.index');
    Route::get('calculations/create', [CalculationController::class, 'create'])->name('calculations.create');
    Route::post('calculations', [CalculationController::class, 'store'])->name('calculations.store');
    Route::get('calculations/{calculation}', [CalculationController::class, 'show'])->withTrashed()->name('calculations.show');
    Route::get('calculations/{calculation}/print', [CalculationController::class, 'print'])->name('calculations.print');
    Route::get('calculations/{calculation}/duplicate', [CalculationController::class, 'duplicate'])->name('calculations.duplicate');
    Route::delete('calculations/{calculation}', [CalculationController::class, 'destroy'])->name('calculations.destroy');
    Route::patch('calculations/{calculation}/restore', [CalculationController::class, 'restore'])->withTrashed()->name('calculations.restore');
    Route::delete('calculations/{calculation}/force', [CalculationController::class, 'forceDestroy'])->withTrashed()->name('calculations.force-destroy');
});
