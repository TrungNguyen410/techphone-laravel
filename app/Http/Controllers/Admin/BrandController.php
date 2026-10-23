<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;

class BrandController extends TaxonomyController
{
    protected function modelClass(): string
    {
        return Brand::class;
    }

    protected function resource(): string
    {
        return 'brands';
    }

    protected function foreignKey(): string
    {
        return 'brand_id';
    }

    protected function meta(): array
    {
        return ['title' => 'Danh sách thương hiệu', 'singular' => 'Thương hiệu', 'columns' => ['Thương hiệu', 'Số sản phẩm', 'Trạng thái']];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Tên thương hiệu', 'type' => 'text', 'required' => true, 'full' => true],
            'logo' => ['label' => 'Logo thương hiệu', 'type' => 'image', 'full' => true],
            'description' => ['label' => 'Mô tả', 'type' => 'textarea', 'full' => true],
            'active' => ['label' => 'Đang hoạt động', 'type' => 'checkbox'],
        ];
    }

    protected function listQuery()
    {
        return Brand::withCount(['products', 'accessories'])->orderBy('name');
    }
}
