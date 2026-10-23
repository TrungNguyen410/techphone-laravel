@extends('layouts.store')

@section('title', 'Đặt hàng thành công')

@section('content')
    <main class="page-shell success-page">
        <div class="container narrow-page">
            <section class="success-card panel">
                <div class="success-icon"><x-icon name="check" /></div>
                <span class="eyebrow">Đặt hàng thành công</span>
                <h1>Cảm ơn bạn đã mua sắm!</h1>
                <p>Đơn hàng đã được tiếp nhận. {{ $store['storeName'] }} sẽ liên hệ xác nhận trong thời gian sớm nhất.</p>
                <div class="order-highlight">
                    <div><small>Mã đơn hàng</small><strong>{{ $order->order_number }}</strong></div>
                    <div><small>Ngày đặt</small><strong>{{ format_date($order->created_at, true) }}</strong></div>
                    <div><small>Trạng thái</small><span class="status-badge {{ $order->statusClass() }}">{{ $order->statusLabel() }}</span></div>
                </div>
                <div class="success-details">
                    <div>
                        <h3>Thông tin người nhận</h3>
                        <p>{{ $order->customer_name }}</p><p>{{ $order->customer_phone }}</p><p>{{ $order->customer_email }}</p><p>{{ $order->fullAddress() }}</p>
                    </div>
                    <div>
                        <h3>Sản phẩm</h3>
                        @foreach ($order->items as $item)
                            <p>{{ $item->name }} <strong>x{{ $item->quantity }}</strong></p>
                        @endforeach
                        <p>Thanh toán: <strong>{{ $order->paymentMethodLabel() }}</strong></p>
                    </div>
                </div>
                @include('partials.order-shipping-note', ['order' => $order])
                <div class="success-total"><span>Tổng thanh toán</span><strong>{{ money($order->total) }}</strong></div>
                <div class="success-actions">
                    <a class="btn btn-light" href="{{ route('home') }}"><x-icon name="home" /> Về trang chủ</a>
                    <a class="btn btn-primary" href="{{ route('orders.lookup', ['order_number' => $order->order_number, 'phone' => $order->customer_phone]) }}"><x-icon name="package" /> Tra cứu đơn</a>
                </div>
            </section>
        </div>
    </main>
@endsection
