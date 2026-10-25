<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Crm\Models\Opportunity;
use Modules\Crm\Models\Order;
use Modules\Crm\Models\Quote;
use Modules\Crm\Models\Task;

/**
 * Календар на събитията и сроковете за един месец.
 *
 * Показва само реални записи: дати на поръчки и запитвания, срокове на
 * оферти и задачи. Няма фиктивни или генерирани събития.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $month = $this->resolveMonth((string) $request->query('month', ''));
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $entries = collect()
            ->merge($this->orders($request, $from, $to))
            ->merge($this->opportunities($request, $from, $to))
            ->merge($this->quotes($request, $from, $to))
            ->merge($this->tasks($request, $from, $to))
            ->sortBy(['date', 'title'])
            ->values();

        return Inertia::render('crm::calendar', [
            'month' => $month->format('Y-m'),
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'today' => Carbon::today()->toDateString(),
            'entries' => $entries,
        ]);
    }

    private function resolveMonth(string $value): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
            } catch (\Throwable) {
                // Невалиден месец в адреса — показваме текущия.
            }
        }

        return Carbon::today()->startOfMonth();
    }

    /** @return array<int, array<string, mixed>> */
    private function orders(Request $request, Carbon $from, Carbon $to): array
    {
        return Order::query()
            ->ownedBy($request->user())
            ->whereNotNull('event_date')
            ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('event_date')
            ->get()
            ->map(fn (Order $order): array => [
                'kind' => 'order',
                'date' => $order->event_date->toDateString(),
                'title' => $order->customer_name_snapshot,
                'subtitle' => $order->order_number.' · '.$order->statusLabel(),
                'url' => route('crm.orders.show', $order),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function opportunities(Request $request, Carbon $from, Carbon $to): array
    {
        return Opportunity::query()
            ->ownedBy($request->user())
            ->with('customer')
            ->open()
            ->whereNotNull('event_date')
            ->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('event_date')
            ->get()
            ->map(fn (Opportunity $opportunity): array => [
                'kind' => 'opportunity',
                'date' => $opportunity->event_date->toDateString(),
                'title' => $opportunity->title,
                'subtitle' => $opportunity->customer?->name.' · '.$opportunity->stageLabel(),
                'url' => route('crm.opportunities.show', $opportunity),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function quotes(Request $request, Carbon $from, Carbon $to): array
    {
        return Quote::query()
            ->ownedBy($request->user())
            ->awaitingReply()
            ->whereNotNull('valid_until')
            ->whereBetween('valid_until', [$from->toDateString(), $to->toDateString()])
            ->orderBy('valid_until')
            ->get()
            ->map(fn (Quote $quote): array => [
                'kind' => 'quote',
                'date' => $quote->valid_until->toDateString(),
                'title' => 'Изтича оферта '.$quote->quote_number,
                'subtitle' => $quote->customer_name_snapshot,
                'url' => route('crm.quotes.show', $quote),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function tasks(Request $request, Carbon $from, Carbon $to): array
    {
        return Task::query()
            ->ownedBy($request->user())
            ->with('customer')
            ->open()
            ->whereNotNull('due_on')
            ->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])
            ->orderBy('due_on')
            ->get()
            ->map(fn (Task $task): array => [
                'kind' => 'task',
                'date' => $task->due_on->toDateString(),
                'title' => $task->title,
                'subtitle' => $task->customer?->name ?? $task->typeLabel(),
                'url' => route('crm.tasks.index'),
            ])
            ->all();
    }
}
