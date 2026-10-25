<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\CompanySettingsController;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Core\Http\Controllers\PricingSettingsController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('settings/pricing', [PricingSettingsController::class, 'edit'])->name('settings.pricing.edit');
    Route::patch('settings/pricing', [PricingSettingsController::class, 'update'])->name('settings.pricing.update');

    Route::get('settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company.edit');
    Route::patch('settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');
});
