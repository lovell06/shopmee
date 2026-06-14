<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    /**
     * Lấy danh sách tất cả các danh mục sản phẩm
     *
     * @return Collection
     */
    public function getAllCategories(): Collection
    {
        return Category::all();
    }
}
