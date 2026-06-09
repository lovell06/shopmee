<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
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
            'cart_item_id' => 'required|integer|exists:cart_items,id',
            'quantity'     => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'cart_item_id.required' => 'Vui lòng chọn sản phẩm trong giỏ hàng.',
            'cart_item_id.exists'   => 'Sản phẩm trong giỏ hàng không tồn tại.',
            'quantity.required'     => 'Vui lòng nhập số lượng.',
            'quantity.min'          => 'Số lượng sản phẩm tối thiểu là 1.',
        ];
    }
}
