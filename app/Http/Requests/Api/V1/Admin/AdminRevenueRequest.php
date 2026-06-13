<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminRevenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shop_id' => 'nullable|integer|exists:shops,id',
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'from_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'to_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:from_date',
        ];
    }
}
