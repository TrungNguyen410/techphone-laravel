@extends('layouts.store')

@section('title', 'Tra cứu đơn hàng')

@section('content')
    <main class="page-shell lookup-page">
        <div class="container">
            <div class="page-hero centered">
                <x-icon name="package" /><span>Tra cứu nhanh chóng</span>
                <h1>Đơn hàng của bạn đang ở đâu?</h1>
                <p>Nhập mã đơn hàng và số điện thoại đã dùng khi đặt hàng.</p>
            </div>
            <form class="lookup-form panel" method="GET" action="{{ route('orders.lookup') }}">
                <label><span>Mã đơn hàng</span><input name="order_number" value="{{ request('order_number') }}" placeholder="Ví dụ: TP260601" data-uppercase required></label>
                <label><span>Số điện thoại</span><input name="phone" value="{{ request('phone') }}" placeholder="0911111111" required></label>
                <button class="btn btn-primary"><x-icon name="search" /> Tra cứu đơn hàng</button>
                <small>Dùng thử: TP260601 · 0911111111</small>
            </form>
            @if ($error)
                <div class="lookup-error panel" role="alert"><x-icon name="alert-circle" /> {{ $error }}</div>
            @endif
            @if ($order)
                @include('partials.order-lookup-result', ['order' => $order])
            @endif
        </div>
    </main>
@endsection
