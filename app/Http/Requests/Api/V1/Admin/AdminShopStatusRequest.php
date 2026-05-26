<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ShopStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminShopStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([ShopStatus::Active->value, ShopStatus::Rejected->value]),
            ],
        ];
    }
}
