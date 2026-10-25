<?php

declare(strict_types=1);

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Modules\Crm\Models\Customer;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Task;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->for($this->user)->create(['name' => 'Мария Иванова']);
    }

    #[Test]
    public function guests_cannot_reach_tasks_or_the_calendar(): void
    {
        $this->get('/crm/tasks')->assertRedirect('/login');
        $this->get('/crm/calendar')->assertRedirect('/login');
    }

    #[Test]
    public function a_task_can_be_created_and_linked_to_a_customer(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', [
            'title' => 'Обаждане за уточнение',
            'type' => 'call',
            'due_on' => '2026-04-10',
            'customer_id' => $this->customer->getKey(),
        ])->assertSessionHasNoErrors();

        $task = Task::query()->firstOrFail();

        $this->assertSame('Обаждане за уточнение', $task->title);
        $this->assertSame($this->customer->getKey(), $task->customer_id);
        $this->assertNull($task->completed_at);
    }

    #[Test]
    public function a_task_without_a_title_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post('/crm/tasks', ['title' => '', 'type' => 'call'])
            ->assertSessionHasErrors('title');
    }

    #[Test]
    public function an_unknown_task_type_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post('/crm/tasks', ['title' => 'Тест', 'type' => 'измислен'])
            ->assertSessionHasErrors('type');
    }

    #[Test]
    public function another_users_customer_cannot_be_attached(): void
    {
        $foreign = Customer::factory()->for(User::factory())->create();

        $this->actingAs($this->user)
            ->post('/crm/tasks', ['title' => 'Тест', 'type' => 'call', 'customer_id' => $foreign->getKey()])
            ->assertSessionHasErrors('customer_id');
    }

    #[Test]
    public function a_task_can_be_completed_and_reopened(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', ['title' => 'Задача', 'type' => 'other']);
        $task = Task::query()->firstOrFail();

        $this->actingAs($this->user)->patch(route('crm.tasks.toggle', $task));
        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($this->user)->patch(route('crm.tasks.toggle', $task));
        $this->assertNull($task->fresh()->completed_at);
    }

    #[Test]
    public function the_list_shows_open_tasks_by_default(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', ['title' => 'Отворена', 'type' => 'other']);
        $this->actingAs($this->user)->post('/crm/tasks', ['title' => 'Изпълнена', 'type' => 'other']);

        $done = Task::query()->where('title', 'Изпълнена')->firstOrFail();
        $this->actingAs($this->user)->patch(route('crm.tasks.toggle', $done));

        $this->actingAs($this->user)
            ->get('/crm/tasks')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::tasks/index')
                ->has('tasks.data', 1)
                ->where('tasks.data.0.title', 'Отворена'));

        $this->actingAs($this->user)
            ->get('/crm/tasks?status=all')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks.data', 2));
    }

    #[Test]
    public function overdue_tasks_are_flagged(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', [
            'title' => 'Просрочена',
            'type' => 'call',
            'due_on' => Carbon::today()->subDays(3)->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get('/crm/tasks?status=overdue')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tasks.data', 1)
                ->where('tasks.data.0.is_overdue', true));
    }

    #[Test]
    public function a_task_due_today_is_not_overdue(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', [
            'title' => 'За днес',
            'type' => 'call',
            'due_on' => Carbon::today()->toDateString(),
        ]);

        $this->actingAs($this->user)
            ->get('/crm/tasks')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('tasks.data.0.is_overdue', false));
    }

    #[Test]
    public function another_users_task_cannot_be_touched(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', ['title' => 'Моя', 'type' => 'other']);
        $task = Task::query()->firstOrFail();

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->patch(route('crm.tasks.toggle', $task))->assertForbidden();
        $this->actingAs($intruder)->delete(route('crm.tasks.destroy', $task))->assertForbidden();
    }

    #[Test]
    public function the_calendar_shows_events_of_the_selected_month(): void
    {
        Opportunity::factory()->for($this->user)->for($this->customer)->create([
            'title' => 'Сватбена украса',
            'event_date' => '2026-05-15',
        ]);

        Opportunity::factory()->for($this->user)->for($this->customer)->create([
            'title' => 'Друг месец',
            'event_date' => '2026-06-15',
        ]);

        $this->actingAs($this->user)
            ->get('/crm/calendar?month=2026-05')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('crm::calendar')
                ->where('month', '2026-05')
                ->has('entries', 1)
                ->where('entries.0.title', 'Сватбена украса')
                ->where('entries.0.kind', 'opportunity'));
    }

    #[Test]
    public function the_calendar_includes_task_deadlines(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', [
            'title' => 'Да се обадя',
            'type' => 'call',
            'due_on' => '2026-05-20',
        ]);

        $this->actingAs($this->user)
            ->get('/crm/calendar?month=2026-05')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('entries', 1)
                ->where('entries.0.kind', 'task'));
    }

    #[Test]
    public function the_calendar_does_not_show_other_users_records(): void
    {
        $other = User::factory()->create();
        Opportunity::factory()->for($other)->for(Customer::factory()->for($other))->create(['event_date' => '2026-05-15']);

        $this->actingAs($this->user)
            ->get('/crm/calendar?month=2026-05')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 0));
    }

    #[Test]
    public function an_invalid_month_falls_back_to_the_current_one(): void
    {
        $this->actingAs($this->user)
            ->get('/crm/calendar?month=не-е-месец')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('month', Carbon::today()->format('Y-m')));
    }

    #[Test]
    public function a_task_goes_to_the_trash_and_can_be_restored(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', [
            'title' => 'Обаждане',
            'type' => 'call',
            'due_on' => '2026-04-10',
            'customer_id' => $this->customer->getKey(),
        ]);

        $task = Task::query()->firstOrFail();

        $this->actingAs($this->user)->delete(route('crm.tasks.destroy', $task))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('crm_tasks', ['id' => $task->getKey()]);

        $this->actingAs($this->user)
            ->get('/crm/tasks?status=all')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks.data', 0));

        $this->actingAs($this->user)
            ->get('/crm/tasks?status=deleted')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tasks.data', 1));

        $this->actingAs($this->user)->patch(route('crm.tasks.restore', $task))->assertSessionHasNoErrors();
        $this->assertNotSoftDeleted('crm_tasks', ['id' => $task->getKey()]);
    }

    #[Test]
    public function a_trashed_task_disappears_from_the_calendar(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', [
            'title' => 'Обаждане',
            'type' => 'call',
            'due_on' => '2026-04-10',
        ]);

        $task = Task::query()->firstOrFail();

        $this->actingAs($this->user)->delete(route('crm.tasks.destroy', $task));

        $this->actingAs($this->user)
            ->get('/crm/calendar?month=2026-04')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 0));
    }

    #[Test]
    public function a_trashed_task_can_be_deleted_forever(): void
    {
        $this->actingAs($this->user)->post('/crm/tasks', ['title' => 'Обаждане', 'type' => 'call']);
        $task = Task::query()->firstOrFail();

        $this->actingAs($this->user)->delete(route('crm.tasks.destroy', $task));
        $this->actingAs($this->user)->delete(route('crm.tasks.force-destroy', $task))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('crm_tasks', 0);
    }
}
