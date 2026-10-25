<?php

declare(strict_types=1);

namespace Modules\Crm\Policies;

use App\Models\User;
use Modules\Crm\Models\Quote;

class QuotePolicy
{
    public function view(User $user, Quote $quote): bool
    {
        return $quote->isOwnedBy($user);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $quote->isOwnedBy($user);
    }

    /** Преместване в Кошчето. */
    public function delete(User $user, Quote $quote): bool
    {
        return $quote->isOwnedBy($user);
    }

    /** Връщане от Кошчето. */
    public function restore(User $user, Quote $quote): bool
    {
        return $quote->isOwnedBy($user);
    }

    /** Окончателно изтриване от базата. */
    public function forceDelete(User $user, Quote $quote): bool
    {
        return $quote->isOwnedBy($user);
    }
}
