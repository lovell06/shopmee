<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Cho phép mọi người dùng (kể cả khách) truy cập danh sách
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search'      => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'shop_id'     => 'nullable|string', 
            'price_min'   => 'nullable|numeric|min:0',
            'price_max'   => 'nullable|numeric|gte:price_min',
            'sort_by'     => 'nullable|string|in:created_at,price',
            'sort_dir'    => 'nullable|string|in:asc,desc',
            'limit'       => 'nullable|integer|min:1|max:100', // Giới hạn bản ghi trên 1 trang
        ];
    }
}
