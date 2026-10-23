<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\Product;
use App\Models\Review;
use App\Services\CatalogService;
use App\Services\MerchandisingService;
use App\Services\ShopperPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'brand', 'price', 'ram', 'storage', 'battery', 'sort']);

        return view('shop.products.index', [
            'products' => $this->catalog->products($filters),
            'filters' => $filters,
            'options' => $this->catalog->productFilterOptions(),
        ]);
    }

    public function show(Product $product, MerchandisingService $merchandising, ShopperPreferences $preferences): View
    {
        abort_unless($product->status === 'active', 404);
        $product->load(['brand', 'category']);

        $catalog = Product::active()->with('brand')->get();
        $recentIds = array_values(array_diff($preferences->recentlyViewed(), [$product->id]));
        $preferences->recordView($product);

        return view('shop.products.show', [
            'product' => $product,
            'reviews' => Review::approved()->where('product_id', $product->id)->latest()->get(),
            'related' => $merchandising->recommendProducts($product, $catalog),
            'compatibleAccessories' => $merchandising->recommendAccessories(
                $product,
                Accessory::active()->with(['brand', 'category'])->get(),
            ),
            'recentlyViewed' => collect($recentIds)->map(fn ($id) => $catalog->firstWhere('id', $id))->filter()->take(4),
            'isCompared' => in_array($product->id, $preferences->compared(), true),
        ]);
    }

    /** Gợi ý tìm kiếm cho header (JSON). */
    public function suggest(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q'));
        if (mb_strlen($keyword) < 2) {
            return response()->json([]);
        }

        return response()->json($this->catalog->suggest($keyword)->map(fn ($item) => [
            'name' => $item->name,
            'brand' => $item->brandName(),
            'price' => money($item->price),
            'image' => media_url($item->image),
            'url' => $item->detailUrl(),
        ]));
    }
}
