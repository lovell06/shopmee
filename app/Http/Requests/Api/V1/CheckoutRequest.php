<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
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
            'user_address_id' => 'required|exists:user_addresses,id',
            // Kiểm tra phương thức thanh toán phải nằm trong danh sách các giá trị Enum của bạn
            'payment_method'  => ['required', Rule::in(PaymentMethod::values())],
            'description'     => 'nullable|string|max:2000'
        ];
    }

    public function messages(): array
    {
        return [
            'user_address_id.required' => 'Vui lòng chọn địa chỉ giao hàng.',
            'user_address_id.exists'   => 'Địa chỉ nhận hàng không tồn tại trên hệ thống.',
            'payment_method.required'  => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.in'        => 'Phương thức thanh toán không hợp lệ.',
        ];
    }
}
