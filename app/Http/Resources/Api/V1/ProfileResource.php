<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Tính tổng tiền mua hàng tích luỹ (orders với payment_status = paid)
        $totalSpent = $this->orders()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->sum('total_amount');

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'role'        => $this->role->value ?? $this->role,
            'status'      => $this->status->value ?? $this->status,
            'total_spent' => (float) $totalSpent,
            'addresses'   => $this->addresses->map(fn($address) => [
                'id'               => $address->id,
                'receiver_name'    => $address->receiver_name,
                'receiver_phone'   => $address->receiver_phone,
                'province'         => $address->province,
                'district'         => $address->district,
                'ward'             => $address->ward,
                'specific_address' => $address->specific_address,
                'is_default'       => $address->is_default,
            ])->toArray(),
            'created_at'  => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at'  => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
