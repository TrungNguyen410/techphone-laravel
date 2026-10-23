<?php

namespace App\Http\Controllers\Admin;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Model;

class BannerController extends SimpleCrudController
{
    protected function modelClass(): string
    {
        return Banner::class;
    }

    protected function resource(): string
    {
        return 'banners';
    }

    protected function meta(): array
    {
        return ['title' => 'Danh sách banner', 'singular' => 'Banner', 'columns' => ['Banner', 'Đường dẫn', 'Thứ tự', 'Trạng thái']];
    }

    protected function fields(): array
    {
        return [
            'title' => ['label' => 'Tiêu đề', 'type' => 'text', 'required' => true],
            'description' => ['label' => 'Mô tả', 'type' => 'text', 'required' => true],
            'image' => ['label' => 'Ảnh banner', 'type' => 'image', 'required' => true, 'full' => true],
            'link' => ['label' => 'Đường dẫn', 'type' => 'text', 'required' => true],
            'position' => ['label' => 'Thứ tự hiển thị', 'type' => 'number'],
            'active' => ['label' => 'Đang hiển thị', 'type' => 'checkbox'],
        ];
    }

    protected function defaults(): array
    {
        return ['link' => '/products', 'position' => 0, 'active' => true];
    }

    protected function rules(?Model $item): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:255'],
            'image_file' => [$item?->image ? 'nullable' : 'required', 'image', 'max:5120'],
            'link' => ['required', 'string', 'max:255', 'regex:/^(\/|https?:\/\/)/'],
            'position' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable'],
        ];
    }

    protected function prepare(array $data, ?Model $item): array
    {
        return [...$data, 'position' => (int) ($data['position'] ?? 0)];
    }

    protected function listQuery()
    {
        return Banner::orderBy('position')->orderByDesc('id');
    }
}
