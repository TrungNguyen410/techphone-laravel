<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;

class ProductController extends CatalogItemController
{
    protected function modelClass(): string
    {
        return Product::class;
    }

    protected function kind(): string
    {
        return 'product';
    }
}
