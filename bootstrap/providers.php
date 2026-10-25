<?php

use App\Providers\AppServiceProvider;
use Modules\Catalog\Providers\CatalogServiceProvider;
use Modules\Core\Providers\CoreServiceProvider;
use Modules\Crm\Providers\CrmServiceProvider;
use Modules\Pricing\Providers\PricingServiceProvider;

return [
    AppServiceProvider::class,

    // Модули на Decorron. Нов модул (Склад, График) се добавя с един ред тук.
    CoreServiceProvider::class,
    CatalogServiceProvider::class,
    PricingServiceProvider::class,
    CrmServiceProvider::class,
];
