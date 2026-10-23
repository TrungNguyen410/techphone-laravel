<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Truy vấn danh sách điện thoại/phụ kiện cho cửa hàng: tìm kiếm, lọc, sắp xếp, phân trang.
 */
class CatalogService
{
    public const PAGE_SIZE = 9;

    public const SORTS = [
        'newest' => 'Mới nhất',
        'price-asc' => 'Giá tăng dần',
        'price-desc' => 'Giá giảm dần',
        'best-selling' => 'Bán chạy',
        'rating' => 'Đánh giá cao',
    ];

    public const PRODUCT_PRICE_RANGES = [
        '0-10000000' => 'Dưới 10 triệu',
        '10000000-20000000' => '10 - 20 triệu',
        '20000000-30000000' => '20 - 30 triệu',
        '30000000-999999999' => 'Trên 30 triệu',
    ];

    public const ACCESSORY_PRICE_RANGES = [
        '0-1000000' => 'Dưới 1 triệu',
        '1000000-3000000' => '1 - 3 triệu',
        '3000000-7000000' => '3 - 7 triệu',
        '7000000-999999999' => 'Trên 7 triệu',
    ];

    /** @param array<string, mixed> $filters */
    public function products(array $filters): LengthAwarePaginator
    {
        $query = Product::active()->with('brand');

        $this->applyKeyword($query, $filters['q'] ?? null, withCategory: false);
        $this->applyBrand($query, $filters['brand'] ?? null);
        $this->applyPrice($query, $filters['price'] ?? null);
        if (! empty($filters['ram'])) {
            $query->where('ram', $filters['ram']);
        }
        if (! empty($filters['storage'])) {
            $query->where('storage', $filters['storage']);
        }
        if (! empty($filters['battery'])) {
            $query->whereRaw('CAST(battery AS UNSIGNED) >= ?', [(int) $filters['battery']]);
        }

        return $this->sort($query, $filters['sort'] ?? 'newest')->paginate(self::PAGE_SIZE)->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    public function accessories(array $filters): LengthAwarePaginator
    {
        $query = Accessory::active()->with(['brand', 'category']);

        $this->applyKeyword($query, $filters['q'] ?? null, withCategory: true);
        $this->applyBrand($query, $filters['brand'] ?? null);
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('name', $filters['category']));
        }
        $this->applyPrice($query, $filters['price'] ?? null);

        return $this->sort($query, $filters['sort'] ?? 'newest')->paginate(self::PAGE_SIZE)->withQueryString();
    }

    /** @return array{brands: Collection, ram: Collection, storage: Collection} */
    public function productFilterOptions(): array
    {
        $products = Product::active()->with('brand')->get();

        return [
            'brands' => $products->map->brandName()->filter()->unique()->sort()->values(),
            'ram' => $products->pluck('ram')->filter()->unique()->sort(SORT_NATURAL)->values(),
            'storage' => $products->pluck('storage')->filter()->unique()->sort(SORT_NATURAL)->values(),
        ];
    }

    /** @return array{brands: Collection, categories: Collection} */
    public function accessoryFilterOptions(): array
    {
        $accessories = Accessory::active()->with(['brand', 'category'])->get();

        return [
            'brands' => $accessories->map->brandName()->filter()->unique()->sort()->values(),
            'categories' => $accessories->map->categoryName()->filter()->unique()->sort()->values(),
        ];
    }

    /**
     * Gợi ý nhanh cho ô tìm kiếm ở header (tối đa 6 kết quả từ cả điện thoại và phụ kiện).
     */
    public function suggest(string $keyword): Collection
    {
        $products = Product::active()->with('brand')->tap(fn ($query) => $this->applyKeyword($query, $keyword, false))->limit(6)->get();
        $accessories = Accessory::active()->with(['brand', 'category'])->tap(fn ($query) => $this->applyKeyword($query, $keyword, true))->limit(6)->get();

        return $products->concat($accessories)->take(6)->values();
    }

    private function applyKeyword(Builder $query, ?string $keyword, bool $withCategory): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $keyword).'%';
        $query->where(function (Builder $inner) use ($like, $withCategory) {
            $inner->where('name', 'like', $like)
                ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', $like));
            if ($withCategory) {
                $inner->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
            }
        });
    }

    private function applyBrand(Builder $query, ?string $brand): void
    {
        if ($brand) {
            $query->whereHas('brand', fn (Builder $inner) => $inner->where('name', $brand));
        }
    }

    private function applyPrice(Builder $query, ?string $range): void
    {
        if ($range && preg_match('/^(\d+)-(\d+)$/', $range, $matches)) {
            $query->whereBetween('price', [(int) $matches[1], (int) $matches[2]]);
        }
    }

    private function sort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price-asc' => $query->orderBy('price'),
            'price-desc' => $query->orderByDesc('price'),
            'best-selling' => $query->orderByDesc('sold'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->latest()->orderByDesc('id'),
        };
    }
}
