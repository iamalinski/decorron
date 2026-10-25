<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Concerns\TrashesRecords;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Http\Requests\TaskRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Http\Resources\TaskResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Task;
use Modules\Crm\Support\CrmOptions;

class TaskController extends Controller
{
    use TrashesRecords;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = [
            'status' => in_array($request->query('status'), ['open', 'completed', 'overdue', 'all', 'deleted'], true)
                ? (string) $request->query('status')
                : 'open',
            'customer_id' => $request->integer('customer_id') ?: null,
        ];

        $tasks = Task::query()
            ->when($filters['status'] === 'deleted', fn ($q) => $q->onlyTrashed())
            ->ownedBy($user)
            ->with([
                'customer' => fn ($relation) => $relation->withTrashed(),
                'opportunity' => fn ($relation) => $relation->withTrashed(),
            ])
            ->when($filters['status'] === 'open', fn ($q) => $q->open())
            ->when($filters['status'] === 'completed', fn ($q) => $q->whereNotNull('completed_at'))
            ->when($filters['status'] === 'overdue', fn ($q) => $q->overdue())
            ->when($filters['customer_id'], fn ($q, $id) => $q->where('customer_id', $id))
            ->orderByRaw('completed_at is not null')
            ->orderByRaw('due_on is null, due_on')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('crm::tasks/index', [
            'tasks' => TaskResource::collection($tasks),
            'filters' => $filters,
            'options' => CrmOptions::forForms(),
            'customers' => CustomerResource::collection(
                Customer::query()->ownedBy($user)->active()->orderBy('name')->limit(50)->get()
            ),
        ]);
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $task = new Task($request->validated());
        $task->user_id = $request->user()->getKey();
        $task->save();

        return back()->with('success', 'Задачата е добавена.');
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return back()->with('success', 'Задачата е обновена.');
    }

    /** Отмятане и връщане в работа. */
    public function toggle(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->completed_at = $task->completed_at === null ? Carbon::now() : null;
        $task->save();

        return back()->with('success', $task->completed_at !== null ? 'Задачата е изпълнена.' : 'Задачата е върната в работа.');
    }

    /** Премества задачата в Кошчето. Към задача не сочи нищо друго. */
    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return back()->with('success', 'Задачата е преместена в Кошчето.');
    }

    public function restore(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('restore', $task);

        if ($error = $this->restoreFromTrash($task)) {
            return back()->withErrors(['delete' => $error]);
        }

        return back()->with('success', 'Задачата е върната от Кошчето.');
    }

    public function forceDestroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('forceDelete', $task);

        $task->forceDelete();

        return back()->with('success', 'Задачата е изтрита окончателно.');
    }
}
