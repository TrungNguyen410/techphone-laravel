<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\SepayWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * VNPay chuyển trình duyệt về đây sau khi khách thanh toán.
     */
    public function vnpayReturn(Request $request, CartService $cart): View
    {
        $result = $this->payments->settleVnpay($request->query());
        $transaction = $result['transaction'] ?? null;
        $order = $transaction?->order;
        $verified = $result['RspCode'] !== '97';
        $success = $verified && in_array($result['RspCode'], ['00', '02'], true) && $transaction?->status === 'paid';

        if ($success) {
            $cart->clear();
        }

        return view('shop.payment-result', [
            'success' => $success,
            'verified' => $verified,
            'reference' => (string) $request->query('vnp_TxnRef', ''),
            'order' => $order,
        ]);
    }

    /**
     * IPN: máy chủ VNPay gọi trực tiếp để xác nhận giao dịch (nguồn xác nhận chính thức).
     */
    public function vnpayIpn(Request $request): JsonResponse
    {
        $result = $this->payments->settleVnpay($request->query());

        return response()->json(['RspCode' => $result['RspCode'], 'Message' => $result['Message']]);
    }

    /**
     * Webhook SePay: ngân hàng báo có tiền chuyển vào tài khoản cửa hàng.
     * Luôn trả 200 khi xác thực đúng (kể cả giao dịch không khớp đơn) để SePay không gửi lại.
     */
    public function sepayWebhook(Request $request, SepayWebhookService $sepay): JsonResponse
    {
        try {
            $result = $sepay->handle($request->json()->all() ?: $request->all(), $request->header('Authorization'));
        } catch (BusinessException $exception) {
            // SePay gọi từ máy chủ (không gửi Accept: application/json) nên luôn trả lỗi dạng JSON.
            return response()->json(['success' => false, 'message' => $exception->getMessage()], $exception->status());
        }

        return response()->json(['success' => true] + $result);
    }

    /** Trang đặt hàng thành công hỏi định kỳ để biết tiền chuyển khoản đã về chưa. */
    public function status(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $order->user_id === $user->id, 403);

        return response()->json([
            'payment_status' => $order->payment_status,
            'payment_label' => $order->paymentStatusLabel(),
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
        ]);
    }

    /** Thanh toán lại một đơn VNPay chưa thành công. */
    public function retry(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id && $order->payment_method === 'card', 403);

        return redirect()->away($this->payments->startVnpayCheckout($order, $request->ip()));
    }
}
