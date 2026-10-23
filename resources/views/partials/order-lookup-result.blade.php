{{-- Kết quả tra cứu đơn: thông tin người nhận được che bớt để bảo vệ dữ liệu cá nhân --}}
@php $currentIndex = array_search($order->status, App\Models\Order::TIMELINE, true); @endphp
<section class="lookup-result panel">
    <div class="lookup-head">
        <div><small>Mã đơn hàng</small><h2>{{ $order->order_number }}</h2><span>Đặt lúc {{ format_date($order->created_at, true) }}</span></div>
        <span class="status-badge {{ $order->statusClass() }}">{{ $order->statusLabel() }}</span>
    </div>
    @if ($order->status === 'cancelled')
        <div class="cancelled-notice">Đơn hàng này đã được hủy.</div>
    @else
        <div class="order-timeline">
            @foreach (App\Models\Order::TIMELINE as $index => $step)
                <div @class(['active' => $currentIndex !== false && $index <= $currentIndex])>
                    <span>@if ($currentIndex !== false && $index <= $currentIndex)<x-icon name="check" />@else{{ $index + 1 }}@endif</span>
                    <strong>{{ App\Models\Order::statusLabelOf($step) }}</strong>
                </div>
            @endforeach
        </div>
    @endif
    <div class="lookup-details">
        <div><h3>Người nhận</h3><p>{{ $order->maskedCustomerName() }}</p><p>{{ $order->maskedCustomerPhone() }}</p></div>
        <div>
            <h3>Sản phẩm</h3>
            @foreach ($order->items as $item)
                <div class="lookup-item"><img src="{{ media_url($item->image) }}" alt=""><span>{{ $item->name }} × {{ $item->quantity }}</span><strong>{{ money($item->total) }}</strong></div>
            @endforeach
        </div>
    </div>
    <div class="success-total"><span>Tổng thanh toán</span><strong>{{ money($order->total) }}</strong></div>
    @include('partials.order-shipping-note', ['order' => $order])
</section>
