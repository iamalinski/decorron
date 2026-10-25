<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Support\CrmOptions;

/**
 * Валидация на поръчка. Договорената сума се изчислява на сървъра от редовете.
 */
class OrderRequest extends FormRequest
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
                $this->route('order') ? 'nullable' : 'required', 'integer',
                Rule::exists('customers', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'crm_opportunity_id' => [
                'nullable', 'integer',
                Rule::exists('crm_opportunities', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'quote_id' => [
                'nullable', 'integer',
                Rule::exists('quotes', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'quote_version' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(CrmOptions::orderStatusValues())],

            'event_date' => ['nullable', 'date'],
            'event_location' => ['nullable', 'string', 'max:255'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'execution_notes' => ['nullable', 'string', 'max:5000'],
            'deadline_note' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.details' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'клиент',
            'event_date' => 'дата на събитието',
            'event_location' => 'място',
            'deposit_amount' => 'капаро',
            'execution_notes' => 'бележки за изпълнението',
            'deadline_note' => 'срокове',
            'items' => 'услуги',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Поръчката трябва да има поне една услуга.',
            'items.min' => 'Поръчката трябва да има поне една услуга.',
            'items.*.description.required' => 'Всяка услуга трябва да има описание.',
        ];
    }
}
