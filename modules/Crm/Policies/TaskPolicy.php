<?php

declare(strict_types=1);

namespace Modules\Crm\Policies;

use App\Models\User;
use Modules\Crm\Models\Task;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $task->isOwnedBy($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $task->isOwnedBy($user);
    }

    /** Преместване в Кошчето. */
    public function delete(User $user, Task $task): bool
    {
        return $task->isOwnedBy($user);
    }

    /** Връщане от Кошчето. */
    public function restore(User $user, Task $task): bool
    {
        return $task->isOwnedBy($user);
    }

    /** Окончателно изтриване от базата. */
    public function forceDelete(User $user, Task $task): bool
    {
        return $task->isOwnedBy($user);
    }
}
