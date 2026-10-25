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
 |   modules/Core/Routes/web.php     — табло, настройки за ценообразуване и данни на бизнеса
 |   modules/Catalog/Routes/web.php  — материали и мерни единици
 |   modules/Pricing/Routes/web.php  — калкулации и история
 |   modules/Crm/Routes/web.php      — клиенти, запитвания, оферти, поръчки, задачи, календар
 */
