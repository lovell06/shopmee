<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
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
        $userId = Auth::id();

        return [
            'name'  => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId . ',id',
            'phone' => 'required|string|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:11',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Vui lòng nhập họ tên.',
            'name.string'     => 'Họ tên phải là chuỗi ký tự.',
            'name.max'        => 'Họ tên không được vượt quá 255 ký tự.',
            'email.required'  => 'Vui lòng nhập Email.',
            'email.email'     => 'Email không đúng định dạng.',
            'email.max'       => 'Email không được vượt quá 255 ký tự.',
            'email.unique'    => 'Email này đã được sử dụng trên hệ thống.',
            'phone.required'  => 'Vui lòng nhập số điện thoại.',
            'phone.regex'     => 'Số điện thoại không đúng định dạng.',
            'phone.min'       => 'Số điện thoại phải có ít nhất 10 chữ số.',
            'phone.max'       => 'Số điện thoại không được vượt quá 11 chữ số.',
        ];
    }
}
