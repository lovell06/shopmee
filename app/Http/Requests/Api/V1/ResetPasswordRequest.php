<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'reset_token' => 'required|string',
            'password'    => 'required|string|min:8|confirmed', // password_confirmation phải khớp
        ];
    }

    public function messages(): array
    {
        return [
            'reset_token.required' => 'Mã xác thực phiên đổi mật khẩu đã hết hạn hoặc không hợp lệ.',
            'password.required'    => 'Vui lòng nhập mật khẩu mới.',
            'password.min'         => 'Mật khẩu mới phải từ 8 ký tự trở lên.',
            'password.confirmed'   => 'Mật khẩu xác nhận không trùng khớp.',
        ];
    }
}
