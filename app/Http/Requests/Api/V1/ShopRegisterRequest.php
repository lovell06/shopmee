<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShopRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Cho phép user đã qua middleware auth sử dụng
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:shops,name|max:255', // Tên shop không được trùng
            'description' => 'nullable|string|max:2000', // Mô tả về shop
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048', // File ảnh logo upload từ máy
        ];
    }
}
