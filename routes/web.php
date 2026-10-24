<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('welcome');
})->name('home');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

/*
 | Маршрутите на модулите се регистрират автоматично от техните
 | service provider-и (вж. App\Support\ModuleServiceProvider):
 |   modules/Core/routes/web.php     — табло и настройки за ценообразуване
 |   modules/Catalog/routes/web.php  — материали и мерни единици
 |   modules/Pricing/routes/web.php  — калкулации и история
 */
