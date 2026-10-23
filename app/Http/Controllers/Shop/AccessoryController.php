<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\Review;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessoryController extends Controller
{
    public function index(Request $request, CatalogService $catalog): View
    {
        $filters = $request->only(['q', 'brand', 'category', 'price', 'sort']);

        return view('shop.accessories.index', [
            'accessories' => $catalog->accessories($filters),
            'filters' => $filters,
            'options' => $catalog->accessoryFilterOptions(),
        ]);
    }

    public function show(Accessory $accessory): View
    {
        abort_unless($accessory->status === 'active', 404);
        $accessory->load(['brand', 'category']);

        return view('shop.accessories.show', [
            'accessory' => $accessory,
            'reviews' => Review::approved()->where('accessory_id', $accessory->id)->latest()->get(),
            'related' => Accessory::active()->with(['brand', 'category'])->whereKeyNot($accessory->id)->latest()->limit(4)->get(),
        ]);
    }
}
