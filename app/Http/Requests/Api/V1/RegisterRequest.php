<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed', // Bắt buộc có trường password_confirmation đi kèm
            'phone'    => 'required|string|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'Vui lòng nhập Email.',
            'email.unique'      => 'Email này đã được sử dụng trên hệ thống.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min'      => 'Mật khẩu phải chứa ít nhất 8 ký tự.',
            'password.confirmed'=> 'Xác nhận mật khẩu không trùng khớp.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex'    => 'Số điện thoại không đúng định dạng.',
            'phone.min'      => 'Số điện thoại phải có ít nhất 10 chữ số.',
        ];
    }
}
