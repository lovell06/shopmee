<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', 'string', Rule::in(UserStatus::values())],
            'role' => ['nullable', 'string', Rule::in(UserRole::values())],
            'limit' => 'nullable|integer|min:1|max:100',
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'from_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
            'to_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:from_date',
        ];
    }
}
