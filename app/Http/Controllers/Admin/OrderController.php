<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): View
    {
        $query = Order::with('items')->latest();

        if ($search = mb_substr(trim((string) $request->query('search')), 0, 100)) {
            $query->where(fn (Builder $inner) => $inner
                ->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%"));
        }
        if (array_key_exists((string) $request->query('status'), Order::STATUSES)) {
            $query->where('status', $request->query('status'));
        }

        return view('admin.orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'selected' => $request->filled('view')
                ? Order::with(['items', 'paymentConfirmer', 'transactions'])->find($request->query('view'))
                : null,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in(array_keys(Order::STATUSES))]]);
        $this->orders->updateStatus($order, $request->input('status'));

        return back()->with('toast', ['type' => 'success', 'message' => "Đã cập nhật đơn {$order->order_number}"]);
    }

    public function updateShipping(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'shipping_provider' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'estimated_delivery' => ['nullable', 'date'],
        ]);
        $this->orders->updateShipping($order, $data);

        return back()->with('toast', ['type' => 'success', 'message' => "Đã cập nhật vận đơn {$order->order_number}"]);
    }

    public function reconcilePayment(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(['paid', 'failed'])],
            'reference' => ['nullable', 'required_if:payment_status,paid', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['reference.required_if' => 'Vui lòng nhập mã tham chiếu thanh toán']);

        $payments->reconcileManual($order, $data['payment_status'], trim((string) ($data['reference'] ?? '')), trim((string) ($data['note'] ?? '')), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => "Đã đối soát thanh toán đơn {$order->order_number}"]);
    }

    public function destroy(Order $order): RedirectResponse
    {
        $this->orders->archive($order);

        return redirect()->route('admin.orders.index')->with('toast', ['type' => 'success', 'message' => "Đã lưu trữ đơn {$order->order_number}"]);
    }
}
