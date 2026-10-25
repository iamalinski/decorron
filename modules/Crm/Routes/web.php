<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Crm\Http\Controllers\CalendarController;
use Modules\Crm\Http\Controllers\CrmOverviewController;
use Modules\Crm\Http\Controllers\CustomerController;
use Modules\Crm\Http\Controllers\OpportunityController;
use Modules\Crm\Http\Controllers\OrderController;
use Modules\Crm\Http\Controllers\QuoteController;
use Modules\Crm\Http\Controllers\TaskController;

Route::middleware(['auth', 'verified'])->prefix('crm')->name('crm.')->group(function (): void {
    Route::get('/', CrmOverviewController::class)->name('overview');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->withTrashed()->name('customers.show');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::patch('customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
    Route::get('customers/{customer}/opportunities', [CustomerController::class, 'opportunities'])->name('customers.opportunities');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::patch('customers/{customer}/restore', [CustomerController::class, 'restore'])->withTrashed()->name('customers.restore');
    Route::delete('customers/{customer}/force', [CustomerController::class, 'forceDestroy'])->withTrashed()->name('customers.force-destroy');

    Route::get('opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
    Route::post('opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
    Route::get('opportunities/{opportunity}', [OpportunityController::class, 'show'])->withTrashed()->name('opportunities.show');
    Route::patch('opportunities/{opportunity}', [OpportunityController::class, 'update'])->name('opportunities.update');
    Route::patch('opportunities/{opportunity}/stage', [OpportunityController::class, 'stage'])->name('opportunities.stage');
    Route::patch('opportunities/{opportunity}/close', [OpportunityController::class, 'close'])->name('opportunities.close');
    Route::post('opportunities/{opportunity}/notes', [OpportunityController::class, 'note'])->name('opportunities.notes.store');
    Route::delete('opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->name('opportunities.destroy');
    Route::patch('opportunities/{opportunity}/restore', [OpportunityController::class, 'restore'])->withTrashed()->name('opportunities.restore');
    Route::delete('opportunities/{opportunity}/force', [OpportunityController::class, 'forceDestroy'])->withTrashed()->name('opportunities.force-destroy');

    Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
    Route::post('quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('quotes/{quote}', [QuoteController::class, 'show'])->withTrashed()->name('quotes.show');
    Route::get('quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::get('quotes/{quote}/print', [QuoteController::class, 'print'])->name('quotes.print');
    Route::patch('quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    Route::patch('quotes/{quote}/status', [QuoteController::class, 'status'])->name('quotes.status');
    Route::delete('quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::patch('quotes/{quote}/restore', [QuoteController::class, 'restore'])->withTrashed()->name('quotes.restore');
    Route::delete('quotes/{quote}/force', [QuoteController::class, 'forceDestroy'])->withTrashed()->name('quotes.force-destroy');
    Route::post('quotes/{quote}/order', [OrderController::class, 'fromQuote'])->name('quotes.order');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->withTrashed()->name('orders.show');
    Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::patch('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::patch('orders/{order}/status', [OrderController::class, 'status'])->name('orders.status');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::patch('orders/{order}/restore', [OrderController::class, 'restore'])->withTrashed()->name('orders.restore');
    Route::delete('orders/{order}/force', [OrderController::class, 'forceDestroy'])->withTrashed()->name('orders.force-destroy');

    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::patch('tasks/{task}/restore', [TaskController::class, 'restore'])->withTrashed()->name('tasks.restore');
    Route::delete('tasks/{task}/force', [TaskController::class, 'forceDestroy'])->withTrashed()->name('tasks.force-destroy');

    Route::get('calendar', CalendarController::class)->name('calendar');
});
