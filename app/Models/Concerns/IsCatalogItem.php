<?php

namespace App\Models\Concerns;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hành vi dùng chung của mặt hàng trong catalog: điện thoại (Product) và phụ kiện (Accessory).
 */
trait IsCatalogItem
{
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('status', 'active')->where('stock', '>', 0);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'active' && $this->stock > 0;
    }

    /**
     * Ảnh đại diện + ảnh phụ, bỏ trùng, tối đa 5 ảnh.
     *
     * @return array<int, string>
     */
    public function galleryImages(): array
    {
        return array_slice(array_values(array_unique(array_filter([$this->image, ...($this->images ?? [])]))), 0, 5);
    }

    public function brandName(): string
    {
        return $this->brand?->name ?? '';
    }

    public function categoryName(): string
    {
        return $this->category?->name ?? '';
    }

    public function detailUrl(): string
    {
        return $this->itemType() === 'accessory'
            ? route('accessories.show', $this)
            : route('products.show', $this);
    }

    /**
     * Khóa định danh duy nhất giữa hai loại mặt hàng, ví dụ "product-5".
     */
    public function itemKey(): string
    {
        return $this->itemType().'-'.$this->id;
    }

    protected static function bootIsCatalogItem(): void
    {
        static::saving(function ($item) {
            $oldPrice = (int) $item->old_price;
            $item->discount_percent = $oldPrice > $item->price
                ? (int) round(($oldPrice - $item->price) / $oldPrice * 100)
                : 0;
        });
    }
}
