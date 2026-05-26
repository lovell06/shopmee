<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminProductStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([ProductStatus::Active->value, ProductStatus::Hidden->value]),
            ],
            'admin_note' => [
                Rule::requiredIf(fn () => $this->input('status') === ProductStatus::Hidden->value),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_note.required' => 'Vui long nhap ly do an san pham.',
        ];
    }
}
