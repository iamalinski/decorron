<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Crm\Support\CrmOptions;

/**
 * Валидация на оферта.
 *
 * Сумите (междинна сума, отстъпка, общо) НЕ се приемат от клиента — те се
 * изчисляват наново в QuoteWriter.
 */
class QuoteRequest extends FormRequest
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
                $this->route('quote') ? 'nullable' : 'required', 'integer',
                Rule::exists('customers', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'crm_opportunity_id' => [
                'nullable', 'integer',
                Rule::exists('crm_opportunities', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'issued_at' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'event_date' => ['nullable', 'date'],
            'event_location' => ['nullable', 'string', 'max:255'],

            'discount_type' => ['nullable', Rule::in(CrmOptions::discountTypeValues())],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'required_with:discount_type'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],

            'terms' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.calculation_id' => [
                'nullable', 'integer',
                Rule::exists('calculations', 'id')->where('user_id', $userId)->whereNull('deleted_at'),
            ],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.details' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /** Отстъпка в проценти не може да надхвърля 100%. */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('discount_type') === 'percent' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Отстъпката в проценти не може да е над 100%.');
            }

            $customerId = $this->input('customer_id') ?? $this->route('quote')?->customer_id;
            $opportunityId = $this->input('crm_opportunity_id');

            if ($customerId === null || $opportunityId === null) {
                return;
            }

            $belongs = DB::table('crm_opportunities')
                ->where('id', (int) $opportunityId)
                ->where('user_id', (int) $this->user()->getKey())
                ->where('customer_id', (int) $customerId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $belongs) {
                $validator->errors()->add('crm_opportunity_id', 'Избраното запитване не е на този клиент.');
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => 'клиент',
            'crm_opportunity_id' => 'запитване',
            'issued_at' => 'дата на офертата',
            'valid_until' => 'валидна до',
            'event_date' => 'дата на събитието',
            'event_location' => 'място',
            'discount_type' => 'вид отстъпка',
            'discount_value' => 'отстъпка',
            'deposit_amount' => 'капаро',
            'terms' => 'условия',
            'internal_notes' => 'вътрешни бележки',
            'items' => 'редове',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Офертата трябва да има поне един ред.',
            'items.min' => 'Офертата трябва да има поне един ред.',
            'items.*.description.required' => 'Всеки ред трябва да има описание.',
            'items.*.quantity.gt' => 'Количеството трябва да е по-голямо от нула.',
            'valid_until.after_or_equal' => 'Валидността не може да е преди датата на офертата.',
        ];
    }
}
