<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkRemoveCartItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'cart_item_ids'   => 'required|array|min:1',
            'cart_item_ids.*' => 'required|integer|exists:cart_items,id',
        ];
    }

    public function messages(): array
    {
        return [
            'cart_item_ids.required' => 'Vui lòng chọn danh sách sản phẩm cần xóa.',
            'cart_item_ids.array'    => 'Danh sách sản phẩm phải là một mảng.',
            'cart_item_ids.min'      => 'Vui lòng chọn ít nhất một sản phẩm để xóa.',
            'cart_item_ids.*.exists' => 'Một trong các sản phẩm cần xóa không tồn tại trong hệ thống.',
        ];
    }
}
