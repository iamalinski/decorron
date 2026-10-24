<?php

declare(strict_types=1);

namespace Modules\Catalog\Policies;

use App\Models\User;
use Modules\Catalog\Models\Unit;

class UnitPolicy
{
    public function view(User $user, Unit $unit): bool
    {
        return $unit->isOwnedBy($user);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $unit->isOwnedBy($user);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $unit->isOwnedBy($user);
    }
}
