<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'user_name', 'product_id', 'accessory_id', 'rating', 'comment', 'images', 'verified_purchase', 'status'])]
class Review extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'rating' => 'integer',
            'verified_purchase' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class)->withTrashed();
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'approved');
    }

    /** Đánh giá chung về dịch vụ cửa hàng (không gắn sản phẩm). */
    public function scopeGeneral(Builder $query): void
    {
        $query->whereNull('product_id')->whereNull('accessory_id');
    }

    public function targetName(): string
    {
        return $this->product?->name ?? $this->accessory?->name ?? 'Dịch vụ cửa hàng';
    }
}
