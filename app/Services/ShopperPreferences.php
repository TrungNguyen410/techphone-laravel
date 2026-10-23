<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Lựa chọn cá nhân của người mua: danh sách yêu thích, so sánh (tối đa 4) và sản phẩm đã xem.
 * Yêu thích của tài khoản lưu trong CSDL, khách vãng lai lưu trong session.
 */
class ShopperPreferences
{
    public const COMPARE_LIMIT = 4;

    private const RECENT_LIMIT = 8;

    /** @var array<int, string>|null */
    private ?array $wishlistCache = null;

    public function __construct(private readonly Session $session) {}

    /** @return array<int, string> khóa dạng "product-5", "accessory-2" */
    public function wishlist(): array
    {
        $user = Auth::user();
        if ($user instanceof User) {
            return $this->wishlistCache ??= $user->wishlistItems()->get()
                ->map(fn ($item) => $item->item_type.'-'.$item->item_id)->all();
        }

        return $this->session->get('wishlist', []);
    }

    public function inWishlist(Product|Accessory $item): bool
    {
        return in_array($item->itemKey(), $this->wishlist(), true);
    }

    /** Thêm/bỏ khỏi yêu thích, trả về true nếu sau thao tác mặt hàng đang được yêu thích. */
    public function toggleWishlist(Product|Accessory $item): bool
    {
        $user = Auth::user();
        if ($user instanceof User) {
            $this->wishlistCache = null;
            $existing = WishlistItem::where('user_id', $user->id)
                ->where('item_type', $item->itemType())
                ->where('item_id', $item->id)
                ->first();
            if ($existing) {
                $existing->delete();

                return false;
            }
            $user->wishlistItems()->create(['item_type' => $item->itemType(), 'item_id' => $item->id]);

            return true;
        }

        $keys = $this->session->get('wishlist', []);
        $added = ! in_array($item->itemKey(), $keys, true);
        $keys = $added ? [...$keys, $item->itemKey()] : array_values(array_diff($keys, [$item->itemKey()]));
        $this->session->put('wishlist', array_slice($keys, -100));

        return $added;
    }

    /** Gộp danh sách yêu thích của phiên khách vào tài khoản sau khi đăng nhập. */
    public function mergeGuestWishlist(User $user): void
    {
        foreach ($this->session->pull('wishlist', []) as $key) {
            [$type, $id] = explode('-', $key, 2);
            WishlistItem::firstOrCreate(['user_id' => $user->id, 'item_type' => $type, 'item_id' => (int) $id]);
        }
    }

    /** @return array<int, int> */
    public function compared(): array
    {
        return $this->session->get('compare', []);
    }

    /**
     * Thêm/bỏ điện thoại khỏi danh sách so sánh. Trả về null nếu danh sách đã đủ 4.
     */
    public function toggleCompare(Product $product): ?bool
    {
        $ids = $this->compared();
        if (in_array($product->id, $ids, true)) {
            $this->session->put('compare', array_values(array_diff($ids, [$product->id])));

            return false;
        }
        if (count($ids) >= self::COMPARE_LIMIT) {
            return null;
        }
        $this->session->put('compare', [...$ids, $product->id]);

        return true;
    }

    /** @return array<int, int> */
    public function recentlyViewed(): array
    {
        return $this->session->get('recently_viewed', []);
    }

    public function recordView(Product $product): void
    {
        $ids = array_values(array_diff($this->recentlyViewed(), [$product->id]));
        $this->session->put('recently_viewed', array_slice([$product->id, ...$ids], 0, self::RECENT_LIMIT));
    }
}
