<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminProductListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', 'string', Rule::in(ProductStatus::values())],
            'shop_id' => 'nullable|integer|exists:shops,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }
}
