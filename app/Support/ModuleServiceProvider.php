<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Базов service provider за всички модули на Decorron.
 *
 * Всеки модул живее в modules/<Име> и е самостоятелен:
 *  - modules/<Име>/Routes/web.php          — маршрути (зареждат се във "web" middleware групата)
 *  - modules/<Име>/Database/Migrations     — миграции
 *  - modules/<Име>/Resources/js/pages       — Inertia страници (разрешават се като "<име>::path/to/page")
 *
 * Добавянето на нов модул (CRM, Склад, График) изисква само нова папка,
 * provider който наследява този клас и ред в bootstrap/providers.php.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Машинното име на модула, използвано като Inertia namespace (напр. "catalog"). */
    protected string $moduleName = '';

    /** Абсолютен път до папката на модула. */
    abstract protected function modulePath(): string;

    public function boot(): void
    {
        $path = rtrim($this->modulePath(), '/\\');

        if (is_dir($migrations = $path.'/Database/Migrations')) {
            $this->loadMigrationsFrom($migrations);
        }

        if (is_file($routes = $path.'/Routes/web.php')) {
            Route::middleware('web')->group($routes);
        }

        $this->registerInertiaNamespace($path);

        $this->bootModule();
    }

    /**
     * Позволява на Inertia-тестовете да намират страниците на модула по
     * namespace ("pricing::calculations/create"). Без това assertInertia()
     * съобщава, че компонентът не съществува.
     */
    private function registerInertiaNamespace(string $path): void
    {
        $pages = $path.'/Resources/js/pages';

        if ($this->moduleName === '' || ! is_dir($pages)) {
            return;
        }

        $this->app->resolving('inertia.testing.view-finder', function ($finder) use ($pages): void {
            $finder->addNamespace($this->moduleName, $pages);
        });
    }

    /** Точка за разширение за специфична за модула логика. */
    protected function bootModule(): void
    {
        //
    }

    public function moduleName(): string
    {
        return $this->moduleName;
    }
}
