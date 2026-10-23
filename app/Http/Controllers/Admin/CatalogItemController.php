<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Services\ImageUploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD dùng chung cho điện thoại và phụ kiện trong trang quản trị.
 * Form thêm/sửa hiển thị dạng modal trên trang danh sách (?create=1 hoặc ?edit={id}).
 */
abstract class CatalogItemController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** "product" hoặc "accessory" */
    abstract protected function kind(): string;

    public function __construct(protected readonly ImageUploader $uploader) {}

    public function index(Request $request): View
    {
        $model = $this->modelClass();
        $query = $model::with(['brand', 'category'])->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")
                ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', "%{$search}%")));
        }
        if ($brand = $request->query('brand')) {
            $query->where('brand_id', $brand);
        }
        if (in_array($request->query('status'), ['active', 'inactive'], true)) {
            $query->where('status', $request->query('status'));
        }

        return view('admin.catalog.index', [
            'kind' => $this->kind(),
            'items' => $query->paginate(15)->withQueryString(),
            'brands' => Brand::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'editing' => $request->filled('edit') ? $model::find($request->query('edit')) : null,
            'creating' => $request->boolean('create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $model = $this->modelClass();
        $model::create($this->payload($request));

        return $this->redirectToIndex('Đã thêm dữ liệu mới');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = $this->modelClass()::findOrFail($id);
        $previousImages = $item->galleryImages();
        $item->update($this->payload($request, $item));

        foreach (array_diff($previousImages, $item->galleryImages()) as $removed) {
            $this->uploader->delete($removed);
        }

        return $this->redirectToIndex('Đã cập nhật dữ liệu');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->modelClass()::findOrFail($id)->delete();

        return $this->redirectToIndex('Đã xóa dữ liệu');
    }

    protected function payload(Request $request, ?Model $item = null): array
    {
        $isProduct = $this->kind() === 'product';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'brand_id' => ['required', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'category_id' => ['required', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'price' => ['required', 'integer', 'min:0'],
            'old_price' => ['nullable', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['required', 'string', 'max:5000'],
            'ram' => [$isProduct ? 'nullable' : 'prohibited', 'string', 'max:30'],
            'storage' => [$isProduct ? 'nullable' : 'prohibited', 'string', 'max:30'],
            'screen' => [$isProduct ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'battery' => [$isProduct ? 'nullable' : 'prohibited', 'string', 'max:50'],
            'camera' => [$isProduct ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'chip' => [$isProduct ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'keep_images' => ['nullable', 'array'],
            'keep_images.*' => ['string'],
            'primary_image' => ['nullable', 'string'],
            'new_images' => ['nullable', 'array', 'max:5'],
            'new_images.*' => ImageUploader::RULES,
        ], [
            'required' => 'Vui lòng nhập :attribute',
            'new_images.*.image' => 'File tải lên phải là ảnh',
            'new_images.*.max' => 'Mỗi ảnh tối đa 5MB',
        ], [
            'name' => 'tên', 'brand_id' => 'thương hiệu', 'category_id' => 'danh mục', 'price' => 'giá bán',
            'stock' => 'tồn kho', 'description' => 'mô tả',
        ]);

        $images = $this->resolveImages($request, $item);
        $brand = Brand::find($data['brand_id']);
        $category = Category::find($data['category_id']);

        $payload = [
            'name' => $data['name'],
            'brand_id' => $data['brand_id'],
            'category_id' => $data['category_id'],
            'price' => $data['price'],
            'old_price' => ($data['old_price'] ?? null) ?: $data['price'],
            'stock' => $data['stock'],
            'status' => $data['status'],
            'description' => $data['description'],
            'image' => $images[0] ?? '',
            'images' => $images,
        ];

        if ($isProduct) {
            $specs = array_map(fn ($value) => trim((string) $value), array_intersect_key($data, array_flip(['ram', 'storage', 'screen', 'battery', 'camera', 'chip'])));
            $payload += $specs;
            $payload['specifications'] = [
                'Màn hình' => $specs['screen'] ?? '',
                'RAM' => $specs['ram'] ?? '',
                'Bộ nhớ trong' => $specs['storage'] ?? '',
                'Pin' => $specs['battery'] ?? '',
                'Camera' => $specs['camera'] ?? '',
                'Chip' => $specs['chip'] ?? '',
            ];
        } else {
            $payload['specifications'] = [
                'Thương hiệu' => $brand?->name ?? '',
                'Loại phụ kiện' => $category?->name ?? '',
                'Bảo hành' => '12 tháng',
            ];
        }

        return $payload;
    }

    /**
     * Ghép ảnh giữ lại + ảnh mới tải lên (tối đa 5), đưa ảnh đại diện lên đầu.
     *
     * @return array<int, string>
     */
    private function resolveImages(Request $request, ?Model $item): array
    {
        $existing = $item?->galleryImages() ?? [];
        $kept = array_values(array_intersect($existing, (array) $request->input('keep_images', [])));
        $uploaded = $this->uploader->storeMany($request->file('new_images'), $this->kind() === 'product' ? 'products' : 'accessories');
        $images = array_slice(array_values(array_unique([...$kept, ...$uploaded])), 0, 5);

        $primary = $request->input('primary_image');
        if ($primary && in_array($primary, $images, true)) {
            $images = [$primary, ...array_values(array_diff($images, [$primary]))];
        }

        return $images;
    }

    private function redirectToIndex(string $message): RedirectResponse
    {
        $route = $this->kind() === 'product' ? 'admin.products.index' : 'admin.accessories.index';

        return redirect()->route($route)->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
