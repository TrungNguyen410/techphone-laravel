<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReviewService
{
    public function __construct(private readonly ImageUploader $uploader) {}

    /**
     * Gửi đánh giá (chờ duyệt). $target = null là đánh giá chung về dịch vụ cửa hàng.
     * Mỗi tài khoản chỉ đánh giá một lần cho mỗi mặt hàng; tự đánh dấu "Đã mua hàng" nếu có đơn đã giao.
     */
    public function create(User $user, Product|Accessory|null $target, int $rating, string $comment, array $images = []): Review
    {
        if ($target && ($target->status !== 'active' || $target->trashed())) {
            throw new BusinessException('Sản phẩm đánh giá không tồn tại', 404);
        }

        $productId = $target instanceof Product ? $target->id : null;
        $accessoryId = $target instanceof Accessory ? $target->id : null;

        $exists = Review::where('user_id', $user->id)
            ->where(fn (Builder $query) => $productId ? $query->where('product_id', $productId) : $query->whereNull('product_id'))
            ->where(fn (Builder $query) => $accessoryId ? $query->where('accessory_id', $accessoryId) : $query->whereNull('accessory_id'))
            ->exists();
        if ($exists) {
            throw new BusinessException($target ? 'Bạn đã đánh giá mặt hàng này rồi' : 'Bạn đã gửi đánh giá dịch vụ rồi', 409);
        }

        $verifiedPurchase = $target !== null && Order::where('user_id', $user->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->whereHas('items', fn (Builder $items) => $target instanceof Product
                ? $items->where('product_id', $target->id)
                : $items->where('accessory_id', $target->id))
            ->exists();

        return Review::create([
            'user_id' => $user->id,
            'user_name' => $user->full_name,
            'product_id' => $productId,
            'accessory_id' => $accessoryId,
            'rating' => $rating,
            'comment' => trim($comment),
            'images' => $this->uploader->storeMany(array_slice($images, 0, 5), 'reviews'),
            'verified_purchase' => $verifiedPurchase,
            'status' => 'pending',
        ]);
    }
}
