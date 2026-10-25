<?php

declare(strict_types=1);

namespace Modules\Crm\Policies;

use App\Models\User;
use Modules\Crm\Models\Order;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->isOwnedBy($user);
    }

    public function update(User $user, Order $order): bool
    {
        return $order->isOwnedBy($user);
    }

    /** Преместване в Кошчето. */
    public function delete(User $user, Order $order): bool
    {
        return $order->isOwnedBy($user);
    }

    /** Връщане от Кошчето. */
    public function restore(User $user, Order $order): bool
    {
        return $order->isOwnedBy($user);
    }

    /** Окончателно изтриване от базата. */
    public function forceDelete(User $user, Order $order): bool
    {
        return $order->isOwnedBy($user);
    }
}
