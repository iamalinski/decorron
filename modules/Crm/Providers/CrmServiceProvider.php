<?php

declare(strict_types=1);

namespace Modules\Crm\Providers;

use App\Support\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Order;
use Modules\Crm\Models\Quote;
use Modules\Crm\Models\Task;
use Modules\Crm\Policies\CustomerPolicy;
use Modules\Crm\Policies\OpportunityPolicy;
use Modules\Crm\Policies\OrderPolicy;
use Modules\Crm\Policies\QuotePolicy;
use Modules\Crm\Policies\TaskPolicy;

class CrmServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName = 'crm';

    protected function modulePath(): string
    {
        return dirname(__DIR__);
    }

    protected function bootModule(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
    }
}
