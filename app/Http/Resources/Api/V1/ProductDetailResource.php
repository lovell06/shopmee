<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'description'   => $this->description,
            'status'        => $this->status->value ?? $this->status,
            'created_at'    => $this->created_at->format('Y-m-d H:i:s'),
            'category'      => [
                'id'   => $this->category_id,
                'name' => $this->category->name ?? null,
            ],
            'shop'          => [
                'id'          => $this->shop_id,
                'name'        => $this->shop->name ?? null,
                'description' => $this->shop->description ?? null,
                'logo_url'    => $this->shop->logo_url ?? null,
            ],
            
            // Format mảng biến thể lồng bên trong sản phẩm
            'variants'      => $this->variants->map(fn($variant) => [
                'id'             => $variant->id,
                'sku'            => $variant->sku,
                'variant_name'   => $variant->variant_name,
                'price'          => number_format($variant->price, 2, '.', ''),
                'stock_quantity' => $variant->stock_quantity,
            ])->toArray(),

            // Format mảng ảnh lồng bên trong sản phẩm
            'images'        => $this->images->map(fn($image) => [
                'id'        => $image->id,
                'image_url' => str_starts_with($image->image, 'http') ? $image->image : asset('storage/' . $image->image),
            ])->toArray(),
        ];
    }
}
