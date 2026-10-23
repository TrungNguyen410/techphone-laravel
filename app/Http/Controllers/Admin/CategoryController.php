<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;

class CategoryController extends TaxonomyController
{
    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function resource(): string
    {
        return 'categories';
    }

    protected function foreignKey(): string
    {
        return 'category_id';
    }

    protected function meta(): array
    {
        return ['title' => 'Danh sách danh mục', 'singular' => 'Danh mục', 'columns' => ['Danh mục', 'Số sản phẩm', 'Trạng thái']];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Tên danh mục', 'type' => 'text', 'required' => true, 'full' => true],
            'description' => ['label' => 'Mô tả', 'type' => 'textarea', 'full' => true],
            'active' => ['label' => 'Đang hoạt động', 'type' => 'checkbox'],
        ];
    }

    protected function listQuery()
    {
        return Category::withCount(['products', 'accessories'])->orderBy('name');
    }
}
