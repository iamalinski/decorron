<?php

declare(strict_types=1);

namespace Modules\Crm\Policies;

use App\Models\User;
use Modules\Crm\Models\Opportunity;

class OpportunityPolicy
{
    public function view(User $user, Opportunity $opportunity): bool
    {
        return $opportunity->isOwnedBy($user);
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $opportunity->isOwnedBy($user);
    }

    /** Преместване в Кошчето. */
    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $opportunity->isOwnedBy($user);
    }

    /** Връщане от Кошчето. */
    public function restore(User $user, Opportunity $opportunity): bool
    {
        return $opportunity->isOwnedBy($user);
    }

    /** Окончателно изтриване от базата. */
    public function forceDelete(User $user, Opportunity $opportunity): bool
    {
        return $opportunity->isOwnedBy($user);
    }
}
