<?php

declare(strict_types=1);

namespace Modules\Crm\Policies;

use App\Models\User;
use Modules\Crm\Models\Customer;

class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $customer->isOwnedBy($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $customer->isOwnedBy($user);
    }

    /** Преместване в Кошчето. */
    public function delete(User $user, Customer $customer): bool
    {
        return $customer->isOwnedBy($user);
    }

    /** Връщане от Кошчето. */
    public function restore(User $user, Customer $customer): bool
    {
        return $customer->isOwnedBy($user);
    }

    /** Окончателно изтриване от базата. */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return $customer->isOwnedBy($user);
    }
}
