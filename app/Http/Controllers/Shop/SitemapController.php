<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\Product;
use Illuminate\Http\Response;

/**
 * Sitemap XML cho công cụ tìm kiếm (SEO).
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $items = Product::active()->latest('updated_at')->get()
            ->concat(Accessory::active()->latest('updated_at')->get());

        return response()
            ->view('shop.sitemap', ['items' => $items])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
