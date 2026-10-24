<?php

declare(strict_types=1);

namespace Modules\Pricing\Providers;

use App\Support\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Pricing\Models\Calculation;
use Modules\Pricing\Policies\CalculationPolicy;
use Modules\Pricing\Services\CalculationRecorder;
use Modules\Pricing\Services\PricingCalculator;
use Modules\Pricing\Support\TaxRates;

class PricingServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'pricing';

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    public function register(): void
    {
        $this->app->bind(TaxRates::class, static fn (): TaxRates => TaxRates::current());
        $this->app->bind(PricingCalculator::class, static fn ($app): PricingCalculator => new PricingCalculator($app->make(TaxRates::class)));
        $this->app->bind(CalculationRecorder::class, static fn ($app): CalculationRecorder => new CalculationRecorder($app->make(PricingCalculator::class)));
    }

    protected function bootModule(): void
    {
        Gate::policy(Calculation::class, CalculationPolicy::class);
    }
}
