<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'type', 'value', 'min_order', 'max_discount', 'quantity', 'used', 'start_date', 'end_date', 'active',
    'assigned_user_id', 'note',
])]
class Voucher extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'percent' => 'Phần trăm',
        'fixed' => 'Số tiền cố định',
        'shipping' => 'Phí vận chuyển',
    ];

    /** Trạng thái voucher riêng hiển thị ở "Voucher của tôi". */
    public const OWNER_STATUSES = [
        'available' => ['label' => 'Dùng được', 'class' => 'status-completed'],
        'upcoming' => ['label' => 'Chưa đến hạn', 'class' => 'status-pending'],
        'used' => ['label' => 'Đã dùng', 'class' => 'status-shipping'],
        'expired' => ['label' => 'Hết hạn', 'class' => 'status-cancelled'],
        'inactive' => ['label' => 'Tạm dừng', 'class' => 'status-cancelled'],
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
            'assigned_user_id' => 'integer',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id')->withTrashed();
    }

    /** Voucher công khai (không phải quà tặng riêng cho một khách). */
    public function scopePublic(Builder $query): void
    {
        $query->whereNull('assigned_user_id');
    }

    public function isGift(): bool
    {
        return $this->assigned_user_id !== null;
    }

    public function ownerStatus(): string
    {
        $today = now()->startOfDay();

        return match (true) {
            $this->quantity > 0 && $this->used >= $this->quantity => 'used',
            $today->gt($this->end_date) => 'expired',
            ! $this->active => 'inactive',
            $today->lt($this->start_date) => 'upcoming',
            default => 'available',
        };
    }

    /** "Giảm 10%, tối đa 500.000 ₫ cho đơn từ 1.000.000 ₫" */
    public function describe(): string
    {
        $benefit = match ($this->type) {
            'percent' => 'Giảm '.$this->value.'%'.($this->max_discount > 0 ? ', tối đa '.money($this->max_discount) : ''),
            'shipping' => 'Giảm phí vận chuyển đến '.money($this->value),
            default => 'Giảm '.money($this->value),
        };

        return $benefit.($this->min_order > 0 ? ' cho đơn từ '.money($this->min_order) : '');
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
