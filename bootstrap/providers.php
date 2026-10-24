<?php

use App\Providers\AppServiceProvider;
use Modules\Catalog\Providers\CatalogServiceProvider;
use Modules\Core\Providers\CoreServiceProvider;
use Modules\Pricing\Providers\PricingServiceProvider;

return [
    AppServiceProvider::class,

    // Модули на Decorron. Нов модул (CRM, Склад, График) се добавя с един ред тук.
    CoreServiceProvider::class,
    CatalogServiceProvider::class,
    PricingServiceProvider::class,
];
