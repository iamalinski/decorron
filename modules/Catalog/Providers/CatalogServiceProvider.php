<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use App\Support\ModuleServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Catalog\Listeners\SeedDefaultUnits;
use Modules\Catalog\Models\Material;
use Modules\Catalog\Models\Unit;
use Modules\Catalog\Policies\MaterialPolicy;
use Modules\Catalog\Policies\UnitPolicy;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'catalog';

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    protected function bootModule(): void
    {
        Gate::policy(Material::class, MaterialPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);

        Event::listen(Registered::class, SeedDefaultUnits::class);
    }
}
