@if ($order->tracking_number)
    <div class="order-shipping-note">
        <strong>{{ $order->shipping_provider ?: 'Đơn vị giao hàng' }}</strong>
        <span>Mã vận đơn: <b>{{ $order->tracking_number }}</b></span>
        @if ($order->estimated_delivery)
            <span>Dự kiến giao: {{ format_date($order->estimated_delivery, true) }}</span>
        @endif
    </div>
@endif
