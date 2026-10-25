<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Support\CrmOptions;

/**
 * Валидация при създаване и редактиране на възможност.
 *
 * Статусът НЕ се сменя оттук — за това има отделни действия, които записват
 * и в хронологията (вж. OpportunityController::stage и ::close).
 */
class OpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = (int) $this->user()->getKey();

        return [
            'customer_id' => [
                'required', 'integer',
                Rule::exists('customers', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:160'],
            'event_type' => ['nullable', 'string', 'max:60'],
            'event_date' => ['nullable', 'date'],
            'event_location' => ['nullable', 'string', 'max:255'],
            'expected_budget' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'source' => ['nullable', Rule::in(CrmOptions::sourceValues())],
            'stage' => ['required', Rule::in(CrmOptions::stageValues())],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_due' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'клиент',
            'title' => 'заглавие',
            'event_type' => 'вид събитие',
            'event_date' => 'дата на събитието',
            'event_location' => 'място',
            'expected_budget' => 'очакван бюджет',
            'source' => 'източник',
            'stage' => 'етап',
            'next_action' => 'следващо действие',
            'next_action_due' => 'срок',
            'notes' => 'бележки',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Изберете клиент за запитването.',
            'customer_id.exists' => 'Избраният клиент не съществува.',
        ];
    }
}
