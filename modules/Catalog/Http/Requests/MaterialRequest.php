<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Material;

class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $material = $this->route('material');
        $materialId = $material instanceof Material ? $material->getKey() : null;
        $userId = (int) $this->user()->getKey();

        return [
            'name' => ['required', 'string', 'max:160'],
            'sku' => [
                'nullable', 'string', 'max:60',
                Rule::unique('materials', 'sku')->where('user_id', $userId)->ignore($materialId),
            ],
            'category' => ['nullable', 'string', 'max:80'],
            'unit_id' => [
                'required', 'integer',
                Rule::exists('units', 'id')->where('user_id', $userId),
            ],
            'default_unit_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'име',
            'sku' => 'код',
            'category' => 'категория',
            'unit_id' => 'мерна единица',
            'default_unit_price' => 'стандартна единична цена',
            'description' => 'описание',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sku.unique' => 'Вече имате материал с този код.',
            'unit_id.exists' => 'Избраната мерна единица не съществува.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'sku' => filled($this->sku) ? trim((string) $this->sku) : null,
            'category' => filled($this->category) ? trim((string) $this->category) : null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
