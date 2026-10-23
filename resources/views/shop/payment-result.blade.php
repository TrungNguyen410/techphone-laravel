@extends('layouts.store')

@section('title', 'Kết quả thanh toán')

@php
    $title = $success ? 'Thanh toán thành công' : 'Giao dịch chưa hoàn tất';
    $description = match (true) {
        $success => 'VNPay đã xác nhận giao dịch. Đơn hàng của bạn đã được chuyển sang trạng thái đã xác nhận.',
        $verified => 'VNPay không ghi nhận thanh toán thành công. Bạn có thể thanh toán lại với cùng đơn hàng.',
        default => 'Kết quả thanh toán chưa được máy chủ xác minh. Giỏ hàng của bạn vẫn được giữ nguyên.',
    };
@endphp

@section('content')
    <main class="page-shell payment-result-page">
        <div class="container narrow-page">
            <section @class(['panel', 'payment-result-card', 'is-accepted' => $success, 'is-failed' => ! $success])>
                <div class="payment-result-icon" aria-hidden="true"><x-icon :name="$success ? 'check-circle' : 'alert-circle'" /></div>
                <span class="eyebrow">Kết quả VNPay</span>
                <h1>{{ $title }}</h1>
                <p>{{ $description }}</p>
                <dl class="payment-result-reference">
                    <div><dt>Mã giao dịch</dt><dd>{{ $reference ?: 'Không có' }}</dd></div>
                    @if ($order)
                        <div><dt>Mã đơn hàng</dt><dd>{{ $order->order_number }}</dd></div>
                    @endif
                </dl>
                <div class="success-actions">
                    <a class="btn btn-primary" href="{{ $order ? route('orders.lookup', ['order_number' => $order->order_number, 'phone' => $order->customer_phone]) : route('orders.lookup') }}"><x-icon name="search" /> Tra cứu đơn</a>
                    @if ($success)
                        <a class="btn btn-light" href="{{ route('products.index') }}">Tiếp tục mua sắm</a>
                    @elseif ($order && $order->status !== 'cancelled' && auth()->id() === $order->user_id)
                        <form method="POST" action="{{ route('orders.pay', $order) }}" class="inline-form">
                            @csrf
                            <button class="btn btn-light">Thử lại thanh toán</button>
                        </form>
                    @else
                        <a class="btn btn-light" href="{{ route('checkout.index') }}">Thử lại thanh toán</a>
                    @endif
                </div>
                <p class="payment-result-help">Nếu tài khoản đã bị trừ tiền nhưng đơn chưa cập nhật, hãy giữ lại mã giao dịch và liên hệ {{ $store['storeName'] }}.</p>
            </section>
        </div>
    </main>
@endsection
