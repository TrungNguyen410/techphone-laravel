<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\Product;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(): View
    {
        return view('shop.cart', [
            'lines' => $this->cart->lines(),
            'subtotal' => $this->cart->subtotal(),
            'discount' => $this->cart->cartDiscount(),
            'voucher' => $this->cart->voucher(),
        ]);
    }

    /**
     * Thêm vào giỏ. Nút "Mua ngay" gửi kèm redirect=checkout.
     */
    public function add(Request $request, string $type, int $id): JsonResponse|RedirectResponse
    {
        $item = ($type === 'accessory' ? Accessory::class : Product::class)::findOrFail($id);
        $quantity = max(1, (int) $request->input('quantity', 1));

        if (! $item->isAvailable()) {
            return $this->respond($request, 'error', $type === 'accessory' ? 'Phụ kiện đã hết hàng' : 'Sản phẩm đã hết hàng');
        }

        $this->cart->add($item, $quantity);

        return match ($request->input('redirect')) {
            'checkout' => redirect()->route('checkout.index'),
            'cart' => redirect()->route('cart.index')->with('toast', ['type' => 'success', 'message' => 'Đã thêm vào giỏ hàng']),
            default => $this->respond($request, 'success', $quantity > 1
                ? "Đã thêm {$quantity} sản phẩm vào giỏ hàng"
                : "Đã thêm {$item->name} vào giỏ hàng"),
        };
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $this->cart->update($key, (int) $request->input('quantity', 1));

        return redirect()->route('cart.index');
    }

    public function remove(string $key): RedirectResponse
    {
        $this->cart->remove($key);

        return redirect()->route('cart.index')->with('toast', ['type' => 'success', 'message' => 'Đã xóa sản phẩm']);
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return redirect()->route('cart.index')->with('toast', ['type' => 'success', 'message' => 'Đã xóa giỏ hàng']);
    }

    public function applyVoucher(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:50']], ['code.required' => 'Vui lòng nhập mã giảm giá']);
        $voucher = $this->cart->applyVoucher($request->string('code'));

        return back()->with('toast', ['type' => 'success', 'message' => "Áp dụng mã {$voucher->code} thành công"]);
    }

    public function removeVoucher(): RedirectResponse
    {
        $this->cart->removeVoucher();

        return back();
    }

    private function respond(Request $request, string $type, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['type' => $type, 'message' => $message, 'count' => $this->cart->count()], $type === 'error' ? 422 : 200);
        }

        return back()->with('toast', ['type' => $type, 'message' => $message]);
    }
}
