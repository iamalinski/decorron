<?php

declare(strict_types=1);

namespace Modules\Catalog\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Modules\Catalog\Database\Seeders\CatalogSeeder;

/**
 * Всеки нов потребител започва със стандартния набор мерни единици,
 * за да може веднага да направи първа калкулация.
 */
class SeedDefaultUnits
{
    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            CatalogSeeder::seedUnitsFor($event->user);
        }
    }
}
