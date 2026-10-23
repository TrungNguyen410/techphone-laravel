<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'provider', 'method', 'status', 'amount', 'currency', 'reference', 'provider_transaction_id',
    'bank_code', 'response_code', 'payment_url', 'raw_response', 'paid_at',
])]
class PaymentTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
