<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Support\CrmOptions;

class TaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(CrmOptions::taskTypeValues())],
            'due_on' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('user_id', $userId)->whereNull('deleted_at')],
            'crm_opportunity_id' => ['nullable', 'integer', Rule::exists('crm_opportunities', 'id')->where('user_id', $userId)->whereNull('deleted_at')],
            'quote_id' => ['nullable', 'integer', Rule::exists('quotes', 'id')->where('user_id', $userId)->whereNull('deleted_at')],
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('user_id', $userId)->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'заглавие',
            'description' => 'описание',
            'type' => 'вид',
            'due_on' => 'срок',
            'customer_id' => 'клиент',
            'crm_opportunity_id' => 'запитване',
        ];
    }
}
