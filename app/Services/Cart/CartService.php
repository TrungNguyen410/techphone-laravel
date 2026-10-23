<?php

namespace App\Services\Cart;

use App\Models\Accessory;
use App\Models\Product;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Giỏ hàng lưu trong session: chỉ giữ khóa mặt hàng và số lượng,
 * giá và tồn kho luôn được đọc lại từ CSDL nên không thể bị sửa phía trình duyệt.
 */
class CartService
{
    private const ITEMS_KEY = 'cart.items';

    private const VOUCHER_KEY = 'cart.voucher';

    private ?Collection $lines = null;

    public function __construct(private readonly Session $session, private readonly VoucherService $vouchers) {}

    /** @return Collection<int, CartLine> */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $stored = $this->session->get(self::ITEMS_KEY, []);
        $ids = ['product' => [], 'accessory' => []];
        foreach (array_keys($stored) as $key) {
            [$type, $id] = explode('-', $key, 2);
            $ids[$type][] = (int) $id;
        }

        $items = Product::with('brand')->whereIn('id', $ids['product'])->get()->keyBy(fn ($item) => $item->itemKey())
            ->union(Accessory::with('brand')->whereIn('id', $ids['accessory'])->get()->keyBy(fn ($item) => $item->itemKey()));

        $lines = collect();
        foreach ($stored as $key => $quantity) {
            if ($item = $items->get($key)) {
                $lines->push(new CartLine($key, $item, max(1, (int) $quantity)));
            }
        }

        return $this->lines = $lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    public function count(): int
    {
        return (int) $this->lines()->sum('quantity');
    }

    public function subtotal(): int
    {
        return (int) $this->lines()->sum(fn (CartLine $line) => $line->lineTotal());
    }

    /**
     * Thêm vào giỏ, không vượt quá tồn kho. Trả về số lượng thực tế trong giỏ.
     */
    public function add(Product|Accessory $item, int $quantity = 1): int
    {
        $items = $this->session->get(self::ITEMS_KEY, []);
        $key = $item->itemKey();
        $next = min(($items[$key] ?? 0) + max(1, $quantity), $item->stock);

        if ($next <= 0) {
            return 0;
        }

        $items[$key] = $next;
        $this->store($items);

        return $next;
    }

    public function update(string $key, int $quantity): void
    {
        $items = $this->session->get(self::ITEMS_KEY, []);
        $line = $this->lines()->firstWhere('key', $key);

        if (! $line) {
            return;
        }

        $items[$key] = max(1, min($quantity, max(1, $line->item->stock)));
        $this->store($items);
    }

    public function remove(string $key): void
    {
        $items = $this->session->get(self::ITEMS_KEY, []);
        unset($items[$key]);
        $this->store($items);
    }

    public function clear(): void
    {
        $this->session->forget([self::ITEMS_KEY, self::VOUCHER_KEY]);
        $this->lines = null;
    }

    public function applyVoucher(string $code): Voucher
    {
        $voucher = $this->vouchers->validate($code, $this->subtotal());
        $this->session->put(self::VOUCHER_KEY, $voucher->code);

        return $voucher;
    }

    public function removeVoucher(): void
    {
        $this->session->forget(self::VOUCHER_KEY);
    }

    public function voucher(): ?Voucher
    {
        $code = $this->session->get(self::VOUCHER_KEY);

        return $code ? Voucher::where('code', $code)->where('active', true)->first() : null;
    }

    /** Giảm giá hiển thị ở giỏ hàng (mã freeship được tính ở bước thanh toán). */
    public function cartDiscount(): int
    {
        $voucher = $this->voucher();

        return $voucher && $voucher->type !== 'shipping' ? $voucher->discountFor($this->subtotal(), 0) : 0;
    }

    /** @param array<string, int> $items */
    private function store(array $items): void
    {
        $this->session->put(self::ITEMS_KEY, $items);
        $this->lines = null;
    }
}
