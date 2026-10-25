<?php

declare(strict_types=1);

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\Crm\Models\Customer;
use Modules\Crm\Support\CrmOptions;

/**
 * Валидация при създаване и редактиране на клиент.
 *
 * Вероятните дубликати не блокират записа завинаги — потребителят може да
 * потвърди съзнателно с confirm_duplicate, защото две различни лица наистина
 * могат да споделят телефон или имейл.
 */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(CrmOptions::customerTypeValues())],
            'name' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(CrmOptions::sourceValues())],
            'notes' => ['nullable', 'string', 'max:5000'],
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => 'тип клиент',
            'name' => 'име',
            'contact_person' => 'лице за контакт',
            'phone' => 'телефон',
            'email' => 'имейл',
            'city' => 'град',
            'address' => 'адрес',
            'source' => 'източник',
            'notes' => 'бележки',
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->boolean('confirm_duplicate')) {
                return;
            }

            $duplicates = $this->findDuplicates();

            if ($duplicates->isEmpty()) {
                return;
            }

            $validator->errors()->add(
                'duplicate',
                'Вероятно вече съществува такъв клиент: '
                .$duplicates->pluck('name')->implode(', ')
                .'. Проверете преди да запазите.',
            );
        });
    }

    /**
     * Клиенти със същия телефон или имейл.
     *
     * @return Collection<int, Customer>
     */
    public function findDuplicates(): Collection
    {
        $phone = Customer::normalizePhone($this->input('phone'));
        $email = trim((string) $this->input('email'));

        if ($phone === null && $email === '') {
            return collect();
        }

        return Customer::query()
            ->ownedBy($this->user())
            ->when($this->route('customer'), fn ($q, $customer) => $q->whereKeyNot($customer->getKey()))
            ->where(function ($query) use ($phone, $email): void {
                if ($phone !== null) {
                    $query->orWhere('phone_normalized', $phone);
                }

                if ($email !== '') {
                    $query->orWhere('email', $email);
                }
            })
            ->limit(5)
            ->get();
    }

    /**
     * Валидираните данни без помощното поле за потвърждение.
     *
     * @return array<string, mixed>
     */
    public function customerAttributes(): array
    {
        return collect($this->validated())->except('confirm_duplicate')->all();
    }
}
