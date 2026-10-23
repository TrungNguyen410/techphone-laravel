<?php

namespace App\Services\Cart;

use App\Models\Accessory;
use App\Models\Product;

final class CartLine
{
    public function __construct(
        public readonly string $key,
        public readonly Product|Accessory $item,
        public readonly int $quantity,
    ) {}

    public function type(): string
    {
        return $this->item->itemType();
    }

    public function lineTotal(): int
    {
        return $this->item->price * $this->quantity;
    }
}
