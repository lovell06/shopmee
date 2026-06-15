<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ShopUpdateRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = Auth::id();
        $shop = Shop::where('owner_id', $userId)->first();
        $shopId = $shop ? $shop->id : null;

        return [
            'name'        => 'required|string|max:255|unique:shops,name,' . $shopId . ',id',
            'description' => 'nullable|string|max:2000',
            'logo'        => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ];
    }

    /**
     * Custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên cửa hàng.',
            'name.string'   => 'Tên cửa hàng phải là chuỗi ký tự.',
            'name.max'      => 'Tên cửa hàng không được vượt quá 255 ký tự.',
            'name.unique'   => 'Tên cửa hàng này đã tồn tại trên hệ thống.',
            'description.string' => 'Mô tả phải là chuỗi ký tự.',
            'description.max'    => 'Mô tả không được vượt quá 2000 ký tự.',
            'logo.image'    => 'Logo phải là tệp ảnh.',
            'logo.mimes'    => 'Logo chỉ chấp nhận định dạng jpeg, png, jpg, gif, svg, webp.',
            'logo.max'      => 'Kích thước ảnh logo không được vượt quá 2MB.',
        ];
    }
}
