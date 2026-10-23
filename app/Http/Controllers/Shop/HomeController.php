<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\Banner;
use App\Models\Product;
use App\Services\MerchandisingService;
use App\Services\ShopperPreferences;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Lối tắt danh mục nổi bật trên trang chủ. */
    private const FEATURED_CATEGORIES = [
        ['name' => 'iPhone', 'icon' => 'smartphone', 'url' => '/products?brand=Apple', 'color' => 'blue'],
        ['name' => 'Samsung', 'icon' => 'smartphone', 'url' => '/products?brand=Samsung', 'color' => 'violet'],
        ['name' => 'Xiaomi', 'icon' => 'zap', 'url' => '/products?brand=Xiaomi', 'color' => 'orange'],
        ['name' => 'Tai nghe', 'icon' => 'headphones', 'url' => '/accessories?category=Tai%20nghe', 'color' => 'cyan'],
        ['name' => 'Đồng hồ', 'icon' => 'watch', 'url' => '/accessories?category=%C4%90%E1%BB%93ng%20h%E1%BB%93', 'color' => 'green'],
    ];

    public function __invoke(MerchandisingService $merchandising, ShopperPreferences $preferences): View
    {
        $products = Product::active()->with('brand')->get();
        $accessories = Accessory::active()->with(['brand', 'category'])->get();
        $history = $preferences->recentlyViewed();

        return view('shop.home', [
            'banners' => Banner::active()->orderBy('position')->get(),
            'categories' => self::FEATURED_CATEGORIES,
            'hotProducts' => $merchandising->bestSellers($products),
            'saleProducts' => $merchandising->bestDeals($products),
            'featuredAccessories' => $merchandising->featuredAccessories($accessories),
            'personalized' => $history ? $merchandising->recommendFromHistory($products, $history) : collect(),
        ]);
    }
}
