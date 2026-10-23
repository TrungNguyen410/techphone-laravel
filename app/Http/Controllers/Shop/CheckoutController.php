<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Services\Cart\CartLine;
use App\Services\Cart\CartService;
use App\Services\OrderService;
use App\Services\Payments\PaymentService;
use App\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request, ShippingService $shipping): View|RedirectResponse
    {
        $user = $request->user();
        $subtotal = $this->cart->subtotal();
        $voucher = $this->cart->voucher();

        // Bảng phí ship + giảm giá theo tỉnh để giao diện tính lại tổng tiền ngay khi đổi tỉnh/thành.
        $quoteFor = function (?string $province) use ($shipping, $subtotal, $voucher) {
            $quote = $shipping->quote($province, $subtotal);

            return $quote + ['discount' => $voucher?->discountFor($subtotal, $quote['fee']) ?? 0];
        };
        $quotes = collect(ShippingService::PROVINCES)->mapWithKeys(fn ($province) => [$province => $quoteFor($province)]);

        return view('shop.checkout', [
            'lines' => $this->cart->lines(),
            'count' => $this->cart->count(),
            'subtotal' => $subtotal,
            'voucher' => $voucher,
            'quotes' => $quotes,
            'defaultQuote' => $quoteFor(old('province')),
            'providers' => $this->payments->providers(),
            'provinces' => ShippingService::PROVINCES,
            'checkoutKey' => old('checkout_key', 'checkout-'.Str::uuid()),
            'paymentReference' => 'TP'.substr((string) now()->getTimestampMs(), -8),
            'user' => $user,
        ]);
    }

    public function store(CheckoutRequest $request, OrderService $orders): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('toast', ['type' => 'error', 'message' => 'Giỏ hàng đang trống']);
        }

        $method = $request->input('payment_method');
        if (! $this->payments->isEnabled($method)) {
            throw new BusinessException('Phương thức thanh toán chưa được hỗ trợ');
        }

        $transfer = trim((string) $request->input('transfer_content'));
        $note = implode("\n", array_filter([
            trim((string) $request->input('note')),
            $method === 'bank' && $transfer ? 'Ma chuyen khoan: '.$transfer : '',
            $method === 'momo' && $transfer ? 'Ma giao dich MoMo: '.$transfer : '',
        ]));

        $order = $orders->create([
            'items' => $this->cart->lines()->map(fn (CartLine $line) => [
                'type' => $line->type(),
                'id' => $line->item->id,
                'quantity' => $line->quantity,
            ])->all(),
            'customer' => $request->only(['full_name', 'email', 'phone', 'address', 'province', 'district', 'ward']),
            'payment_method' => $method,
            'voucher_code' => $this->cart->voucher()?->code,
            'note' => $note,
        ], $request->user(), $request->input('checkout_key'));

        if ($method === 'card') {
            // Giữ giỏ hàng cho tới khi VNPay báo thanh toán thành công.
            return redirect()->away($this->payments->startVnpayCheckout($order, $request->ip()));
        }

        $this->cart->clear();

        return redirect()->route('orders.success', $order);
    }
}
