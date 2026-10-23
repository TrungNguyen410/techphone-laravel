<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'type', 'value', 'min_order', 'max_discount', 'quantity', 'used', 'start_date', 'end_date', 'active'])]
class Voucher extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'percent' => 'Phần trăm',
        'fixed' => 'Số tiền cố định',
        'shipping' => 'Phí vận chuyển',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_order' => 'integer',
            'max_discount' => 'integer',
            'quantity' => 'integer',
            'used' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'active' => 'boolean',
        ];
    }

    protected function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    /**
     * Số tiền giảm cho một đơn có tạm tính và phí giao hàng cho trước.
     */
    public function discountFor(int $subtotal, int $shippingFee): int
    {
        if ($subtotal < $this->min_order) {
            return 0;
        }

        return (int) match ($this->type) {
            'percent' => min(intdiv($subtotal * $this->value, 100), $this->max_discount ?: PHP_INT_MAX),
            'fixed' => min($this->value, $subtotal),
            'shipping' => min($this->value, $shippingFee),
            default => 0,
        };
    }

    public function valueLabel(): string
    {
        return $this->type === 'percent' ? $this->value.'%' : money($this->value);
    }
}
