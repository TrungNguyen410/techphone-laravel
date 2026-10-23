<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function success(Request $request, Order $order): View
    {
        $this->authorizeOwner($request, $order);

        return view('shop.orders.success', ['order' => $order->load('items')]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->orders->cancel($order, $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Đã hủy đơn hàng']);
    }

    /**
     * Đặt lại: thêm các sản phẩm còn bán của đơn cũ vào giỏ (số lượng không vượt tồn kho).
     */
    public function reorder(Request $request, Order $order, CartService $cart): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $unavailable = [];
        $added = 0;
        foreach ($order->items as $item) {
            $current = $item->type === 'accessory' ? $item->accessory : $item->product;
            if (! $current || $current->trashed() || ! $current->isAvailable()) {
                $unavailable[] = $item->name;

                continue;
            }
            $cart->add($current, min($item->quantity, $current->stock));
            $added++;
        }

        $redirect = back();
        if ($added) {
            $redirect->with('toast', ['type' => 'success', 'message' => 'Đã thêm lại sản phẩm còn hàng vào giỏ hàng']);
        }
        if ($unavailable) {
            $redirect->with($added ? 'toast_extra' : 'toast', ['type' => 'error', 'message' => 'Không thể thêm: '.implode(', ', $unavailable)]);
        }

        return $redirect;
    }

    /**
     * Tra cứu đơn bằng mã đơn + số điện thoại người nhận (không cần đăng nhập, thông tin được che bớt).
     */
    public function lookup(Request $request): View
    {
        $order = null;
        $error = null;

        if ($request->filled('order_number') || $request->filled('phone')) {
            try {
                if (! $request->filled('order_number')) {
                    throw new BusinessException('Vui lòng nhập mã đơn hàng');
                }
                $order = $this->orders->lookup($request->string('order_number'), $request->string('phone'));
            } catch (BusinessException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('shop.orders.lookup', ['order' => $order, 'error' => $error]);
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $order->user_id === $user->id, 403, 'Bạn không có quyền truy cập đơn hàng này');
    }
}
