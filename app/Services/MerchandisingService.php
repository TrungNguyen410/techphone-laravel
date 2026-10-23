<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Các quy tắc trưng bày và gợi ý sản phẩm trên trang chủ / trang chi tiết.
 */
class MerchandisingService
{
    private const UNIVERSAL_ACCESSORY_CATEGORIES = ['sạc', 'cáp sạc', 'pin dự phòng', 'bảo vệ màn hình', 'giá đỡ'];

    /** Sản phẩm bán chạy: còn hàng, xếp theo số lượng đã bán. */
    public function bestSellers(Collection $items, int $limit = 8): Collection
    {
        return $items->filter->isAvailable()->sortByDesc('sold')->take($limit)->values();
    }

    /** Ưu đãi tốt nhất: giảm từ 10%, ưu tiên số tiền tiết kiệm lớn. */
    public function bestDeals(Collection $items, int $limit = 8): Collection
    {
        return $items
            ->filter(fn ($item) => $item->isAvailable() && $item->old_price > $item->price && $item->discount_percent >= 10)
            ->sort(fn ($a, $b) => (($b->old_price - $b->price) <=> ($a->old_price - $a->price)) ?: ($b->discount_percent <=> $a->discount_percent))
            ->take($limit)
            ->values();
    }

    /** Phụ kiện nổi bật: từ 4.5 sao và ít nhất 20 lượt bán. */
    public function featuredAccessories(Collection $items, int $limit = 8): Collection
    {
        return $items
            ->filter(fn ($item) => $item->isAvailable() && $item->rating >= 4.5 && $item->sold >= 20)
            ->sortByDesc(fn ($item) => $item->rating * 100 + $item->sold)
            ->take($limit)
            ->values();
    }

    /** Điểm tương đồng: cùng hãng, cùng RAM/bộ nhớ, giá chênh không quá 20%. */
    public function similarityScore(Product $anchor, Product $candidate): ?float
    {
        if ($anchor->id === $candidate->id || ! $candidate->isAvailable()) {
            return null;
        }

        $score = 0.0;
        if (mb_strtolower($anchor->brandName()) === mb_strtolower($candidate->brandName())) {
            $score += 5;
        }
        if (mb_strtolower($anchor->ram) === mb_strtolower($candidate->ram)) {
            $score += 2;
        }
        if (mb_strtolower($anchor->storage) === mb_strtolower($candidate->storage)) {
            $score += 2;
        }
        if ($anchor->price > 0 && abs($candidate->price - $anchor->price) / $anchor->price <= 0.2) {
            $score += 3;
        }

        return $score + min($candidate->rating, 5) / 10;
    }

    public function recommendProducts(Product $anchor, Collection $products, int $limit = 4): Collection
    {
        return $products
            ->map(fn (Product $item) => ['item' => $item, 'score' => $this->similarityScore($anchor, $item)])
            ->filter(fn ($entry) => $entry['score'] !== null)
            ->sort(fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($b['item']->sold <=> $a['item']->sold))
            ->take($limit)
            ->pluck('item')
            ->values();
    }

    /** Gợi ý dựa trên sản phẩm đã xem gần nhất, chưa có lịch sử thì lấy hàng bán chạy. */
    public function recommendFromHistory(Collection $products, array $historyIds, int $limit = 4): Collection
    {
        $anchor = collect($historyIds)->map(fn ($id) => $products->firstWhere('id', $id))->filter()->first();

        return $anchor ? $this->recommendProducts($anchor, $products, $limit) : $this->bestSellers($products, $limit);
    }

    /** Phụ kiện mua kèm: cùng hãng với điện thoại hoặc loại dùng chung (sạc, cáp, pin...). */
    public function recommendAccessories(Product $product, Collection $accessories, int $limit = 4): Collection
    {
        return $accessories
            ->filter->isAvailable()
            ->map(function (Accessory $item) use ($product) {
                $sameBrand = mb_strtolower($item->brandName()) === mb_strtolower($product->brandName());
                $universal = in_array(mb_strtolower($item->categoryName()), self::UNIVERSAL_ACCESSORY_CATEGORIES, true);

                return ['item' => $item, 'score' => ($sameBrand ? 4 : 0) + ($universal ? 2 : 0) + $item->rating / 10];
            })
            ->filter(fn ($entry) => $entry['score'] >= 2)
            ->sort(fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($b['item']->sold <=> $a['item']->sold))
            ->take($limit)
            ->pluck('item')
            ->values();
    }
}
