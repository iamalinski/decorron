<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'core';

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }
}
