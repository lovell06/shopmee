<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
            'email' => 'required|email|exists:users,email', // Email phải tồn tại trong hệ thống thì mới cho lấy lại
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Vui lòng nhập tài khoản Email.',
            'email.exists'   => 'Email này chưa được đăng ký trên hệ thống.',
        ];
    }
}
