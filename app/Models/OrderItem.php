<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'type', 'product_id', 'accessory_id', 'name', 'image', 'price', 'quantity', 'total'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
            'total' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class)->withTrashed();
    }

    public function catalogId(): ?int
    {
        return $this->type === 'accessory' ? $this->accessory_id : $this->product_id;
    }
}
