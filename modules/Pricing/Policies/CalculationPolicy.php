<?php

declare(strict_types=1);

namespace Modules\Pricing\Policies;

use App\Models\User;
use Modules\Pricing\Models\Calculation;

class CalculationPolicy
{
    public function view(User $user, Calculation $calculation): bool
    {
        return $calculation->isOwnedBy($user);
    }

    public function update(User $user, Calculation $calculation): bool
    {
        return $calculation->isOwnedBy($user);
    }

    public function delete(User $user, Calculation $calculation): bool
    {
        return $calculation->isOwnedBy($user);
    }
}
