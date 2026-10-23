<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Danh mục và thương hiệu: tên/slug không trùng, không xóa khi còn sản phẩm sử dụng.
 */
abstract class TaxonomyController extends SimpleCrudController
{
    /** Cột khóa ngoại trên bảng sản phẩm: category_id / brand_id */
    abstract protected function foreignKey(): string;

    protected function defaults(): array
    {
        return ['active' => true];
    }

    protected function rules(?Model $item): array
    {
        $table = (new ($this->modelClass()))->getTable();

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique($table, 'name')->ignore($item)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['nullable'],
        ] + (array_key_exists('logo', $this->fields()) ? ['logo_file' => ['nullable', 'image', 'max:5120']] : []);
    }

    protected function prepare(array $data, ?Model $item): array
    {
        return [...$data, 'slug' => Str::slug($data['name'])];
    }

    protected function beforeDelete(Model $item): void
    {
        $inUse = Product::where($this->foreignKey(), $item->id)->exists()
            || Accessory::where($this->foreignKey(), $item->id)->exists();

        if ($inUse) {
            throw new BusinessException('Không thể xóa '.mb_strtolower($this->meta()['singular']).' đang được sản phẩm sử dụng', 409);
        }
    }
}
