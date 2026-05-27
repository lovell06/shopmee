<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Kiểm tra ID biến thể phải tồn tại trong bảng product_variants
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            // Số lượng mua tối thiểu là 1
            'quantity'           => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'product_variant_id.required' => 'Vui lòng chọn sản phẩm.',
            'product_variant_id.exists'   => 'Sản phẩm hoặc biến thể này không tồn tại.',
            'quantity.required'           => 'Vui lòng nhập số lượng.',
            'quantity.min'                => 'Số lượng mua tối thiểu phải từ 1 sản phẩm.',
        ];
    }
}
