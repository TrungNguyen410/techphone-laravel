<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\Product;
use App\Services\ShopperPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Yêu thích và so sánh sản phẩm.
 */
class PreferenceController extends Controller
{
    public function __construct(private readonly ShopperPreferences $preferences) {}

    public function favorites(): View
    {
        $keys = collect($this->preferences->wishlist());
        $ids = fn (string $type) => $keys->filter(fn ($key) => str_starts_with($key, $type.'-'))
            ->map(fn ($key) => (int) substr($key, strlen($type) + 1))->all();

        return view('shop.favorites', [
            'products' => Product::active()->with('brand')->whereIn('id', $ids('product'))->get(),
            'accessories' => Accessory::active()->with(['brand', 'category'])->whereIn('id', $ids('accessory'))->get(),
        ]);
    }

    public function toggleWishlist(Request $request, string $type, int $id): JsonResponse|RedirectResponse
    {
        $item = ($type === 'accessory' ? Accessory::class : Product::class)::findOrFail($id);
        $added = $this->preferences->toggleWishlist($item);
        $message = $added ? 'Đã thêm vào danh sách yêu thích' : 'Đã bỏ khỏi danh sách yêu thích';

        if ($request->expectsJson()) {
            return response()->json([
                'active' => $added,
                'count' => count($this->preferences->wishlist()),
                'message' => $message,
            ]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }

    public function compare(): View
    {
        $ids = $this->preferences->compared();
        $products = Product::with('brand')->whereIn('id', $ids)->get()->sortBy(fn ($product) => array_search($product->id, $ids))->values();

        $rows = [
            'Giá hiện tại' => fn (Product $item) => money($item->price),
            'Thương hiệu' => fn (Product $item) => $item->brandName(),
            'RAM' => fn (Product $item) => $item->ram,
            'Bộ nhớ' => fn (Product $item) => $item->storage,
            'Màn hình' => fn (Product $item) => $item->screen,
            'Pin' => fn (Product $item) => $item->battery,
            'Đánh giá' => fn (Product $item) => $item->rating.'/5',
            'Tồn kho' => fn (Product $item) => $item->stock > 0 ? 'Còn '.$item->stock : 'Hết hàng',
        ];

        return view('shop.compare', [
            'products' => $products,
            'rows' => collect($rows)->map(function ($render) use ($products) {
                $values = $products->map($render)->all();

                return ['values' => $values, 'different' => count(array_unique($values)) > 1];
            }),
        ]);
    }

    public function toggleCompare(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $result = $this->preferences->toggleCompare($product);
        $count = count($this->preferences->compared());
        [$type, $message] = match ($result) {
            null => ['info', 'Danh sách so sánh đã đủ 4 sản phẩm. Hãy bỏ một sản phẩm trước.'],
            true => ['info', "Đã thêm vào so sánh ({$count}/4)"],
            false => ['info', 'Đã bỏ khỏi danh sách so sánh'],
        };

        if ($request->expectsJson()) {
            return response()->json(['active' => (bool) $result, 'count' => $count, 'message' => $message]);
        }

        return back()->with('toast', ['type' => $type, 'message' => $message]);
    }
}
