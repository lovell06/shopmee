<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
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
            'receiver_name'    => 'required|string|max:50', 
            'receiver_phone'   => 'required|string|max:20', 
            'province'         => 'required|string|max:100', 
            'district'         => 'required|string|max:100',
            'ward'             => 'required|string|max:100',
            'specific_address' => 'required|string|max:255',
            'is_default'       => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_name.required'    => 'Vui lòng nhập tên người nhận.',
            'receiver_name.max'         => 'Tên người nhận không được vượt quá 50 ký tự.',
            'receiver_phone.required'   => 'Vui lòng nhập số điện thoại người nhận.',
            'province.required'         => 'Vui lòng nhập Tỉnh/Thành phố.',
            'district.required'         => 'Vui lòng nhập Quận/Huyện.',
            'ward.required'             => 'Vui lòng nhập Phường/Xã.',
            'specific_address.required' => 'Vui lòng nhập địa chỉ cụ thể.',
        ];
    }
}
