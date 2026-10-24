<?php

declare(strict_types=1);

namespace Modules\Catalog\Policies;

use App\Models\User;
use Modules\Catalog\Models\Material;

class MaterialPolicy
{
    public function view(User $user, Material $material): bool
    {
        return $material->isOwnedBy($user);
    }

    public function update(User $user, Material $material): bool
    {
        return $material->isOwnedBy($user);
    }

    public function delete(User $user, Material $material): bool
    {
        return $material->isOwnedBy($user);
    }
}
