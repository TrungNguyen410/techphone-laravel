<?php

namespace App\Models;

use App\Models\Concerns\IsCatalogItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'brand_id', 'category_id', 'price', 'old_price', 'image', 'images',
    'description', 'specifications', 'stock', 'sold', 'rating', 'status',
])]
class Accessory extends Model
{
    use IsCatalogItem, SoftDeletes;

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'specifications' => 'array',
            'price' => 'integer',
            'old_price' => 'integer',
            'stock' => 'integer',
            'sold' => 'integer',
            'rating' => 'float',
        ];
    }

    public function itemType(): string
    {
        return 'accessory';
    }
}
