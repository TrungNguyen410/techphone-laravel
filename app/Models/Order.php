<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'order_number', 'idempotency_key', 'request_fingerprint', 'user_id', 'status', 'subtotal', 'shipping_fee',
    'discount', 'total', 'payment_method', 'payment_status', 'payment_reference', 'payment_confirmed_by',
    'payment_confirmed_at', 'payment_note', 'shipping_provider', 'tracking_number', 'estimated_delivery',
    'customer_name', 'customer_email', 'customer_phone', 'customer_address', 'province', 'district', 'ward',
    'note', 'voucher_code', 'voucher_usage_released',
])]
class Order extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'pending' => ['label' => 'Chờ xác nhận', 'class' => 'status-pending'],
        'confirmed' => ['label' => 'Đã xác nhận', 'class' => 'status-confirmed'],
        'shipping' => ['label' => 'Đang giao hàng', 'class' => 'status-shipping'],
        'delivered' => ['label' => 'Đã giao hàng', 'class' => 'status-delivered'],
        'completed' => ['label' => 'Hoàn thành', 'class' => 'status-completed'],
        'cancelled' => ['label' => 'Đã hủy', 'class' => 'status-cancelled'],
    ];

    /** Các bước chuyển trạng thái hợp lệ. */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['delivered', 'completed'],
        'delivered' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public const TIMELINE = ['pending', 'confirmed', 'shipping', 'delivered', 'completed'];

    public const PAYMENT_METHODS = [
        'cod' => 'Thanh toán khi nhận hàng (COD)',
        'bank' => 'Chuyển khoản ngân hàng',
        'momo' => 'Ví điện tử MoMo',
        'card' => 'Thẻ/QR qua VNPay',
    ];

    public const PAYMENT_STATUSES = [
        'pending' => 'Chờ thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán thất bại',
        'refund_required' => 'Cần hoàn tiền',
        'refunded' => 'Đã hoàn tiền',
    ];

    public const SETTLED_PAYMENT_STATUSES = ['paid', 'refund_required', 'refunded'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'shipping_fee' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'payment_confirmed_at' => 'datetime',
            'estimated_delivery' => 'datetime',
            'voucher_usage_released' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public static function statusLabelOf(string $status): string
    {
        return self::STATUSES[$status]['label'] ?? $status;
    }

    public function statusLabel(): string
    {
        return self::statusLabelOf($this->status);
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status]['class'] ?? 'status-pending';
    }

    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    /** @return array<int, string> */
    public function nextStatuses(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function isPaymentSettled(): bool
    {
        return in_array($this->payment_status, self::SETTLED_PAYMENT_STATUSES, true);
    }

    /**
     * Khách được tự hủy khi đơn chưa giao và chưa thanh toán.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true) && ! $this->isPaymentSettled();
    }

    public function fullAddress(): string
    {
        return implode(', ', array_filter([$this->customer_address, $this->ward, $this->district, $this->province]));
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function maskedCustomerName(): string
    {
        return mb_substr($this->customer_name, 0, 1).'***';
    }

    public function maskedCustomerPhone(): string
    {
        return substr($this->customer_phone, 0, 3).'****'.substr($this->customer_phone, -3);
    }
}
