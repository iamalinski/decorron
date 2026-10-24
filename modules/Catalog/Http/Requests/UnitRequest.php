<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Unit;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $unit = $this->route('unit');
        $unitId = $unit instanceof Unit ? $unit->getKey() : null;
        $userId = (int) $this->user()->getKey();

        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('units', 'name')->where('user_id', $userId)->ignore($unitId),
            ],
            'symbol' => [
                'required', 'string', 'max:20',
                Rule::unique('units', 'symbol')->where('user_id', $userId)->ignore($unitId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'име',
            'symbol' => 'символ',
            'description' => 'описание',
            'is_active' => 'статус',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'Вече имате мерна единица с това име.',
            'symbol.unique' => 'Вече имате мерна единица с този символ.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'symbol' => is_string($this->symbol) ? trim($this->symbol) : $this->symbol,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
