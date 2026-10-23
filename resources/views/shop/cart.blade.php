@extends('layouts.store')

@section('title', 'Giỏ hàng')

@section('content')
    <main class="page-shell">
        @if ($lines->isEmpty())
            <div class="container narrow-page">
                @include('partials.empty-state', [
                    'title' => 'Giỏ hàng đang trống',
                    'description' => 'Thêm sản phẩm bạn yêu thích để tiếp tục mua sắm.',
                    'actionLabel' => 'Khám phá sản phẩm',
                    'actionUrl' => route('products.index'),
                ])
            </div>
        @else
            @php $shippingVoucherPending = $voucher?->type === 'shipping'; @endphp
            <div class="container">
                <nav class="breadcrumbs"><a href="{{ route('home') }}">Trang chủ</a><x-icon name="chevron-right" /><span>Giỏ hàng</span></nav>
                <div class="page-title-row">
                    <div><span class="eyebrow">Sản phẩm đã chọn</span><h1>Giỏ hàng của bạn</h1></div>
                    <form method="POST" action="{{ route('cart.clear') }}" data-confirm="Tất cả sản phẩm và mã giảm giá đã áp dụng sẽ bị xóa." data-confirm-title="Xóa toàn bộ giỏ hàng?">
                        @csrf @method('DELETE')
                        <button class="text-danger-button"><x-icon name="trash-2" /> Xóa toàn bộ</button>
                    </form>
                </div>
                <div class="cart-layout">
                    <section class="cart-items panel">
                        @foreach ($lines as $line)
                            @php $item = $line->item; @endphp
                            <article class="cart-item">
                                <a href="{{ $item->detailUrl() }}"><img src="{{ media_url($item->image) }}" alt="{{ $item->name }}"></a>
                                <div class="cart-item-info">
                                    <a href="{{ $item->detailUrl() }}"><h3>{{ $item->name }}</h3></a>
                                    <span class="stock-note">Còn {{ $item->stock }} sản phẩm</span>
                                    <strong>{{ money($item->price) }}</strong>
                                </div>
                                <div class="quantity-control">
                                    <form method="POST" action="{{ route('cart.update', $line->key) }}" class="inline-form">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ $line->quantity - 1 }}">
                                        <button @disabled($line->quantity <= 1) aria-label="Giảm số lượng"><x-icon name="minus" /></button>
                                    </form>
                                    <span>{{ $line->quantity }}</span>
                                    <form method="POST" action="{{ route('cart.update', $line->key) }}" class="inline-form">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="quantity" value="{{ $line->quantity + 1 }}">
                                        <button @disabled($line->quantity >= $item->stock) aria-label="Tăng số lượng"><x-icon name="plus" /></button>
                                    </form>
                                </div>
                                <strong class="cart-line-total">{{ money($line->lineTotal()) }}</strong>
                                <form method="POST" action="{{ route('cart.remove', $line->key) }}" class="inline-form" data-confirm="Sản phẩm sẽ được xóa khỏi giỏ hàng của bạn." data-confirm-title="Xóa sản phẩm?">
                                    @csrf @method('DELETE')
                                    <button class="remove-item" aria-label="Xóa sản phẩm"><x-icon name="trash-2" /></button>
                                </form>
                            </article>
                        @endforeach
                    </section>

                    <aside class="cart-summary panel">
                        <h3>Tóm tắt đơn hàng</h3>
                        @include('partials.voucher-box', ['voucher' => $voucher])
                        <div class="summary-lines">
                            <div><span>Tạm tính</span><strong>{{ money($subtotal) }}</strong></div>
                            <div><span>Phí vận chuyển</span><strong>Tính theo tỉnh/thành ở bước thanh toán</strong></div>
                            @if ($shippingVoucherPending)
                                <div><span>Ưu đãi vận chuyển</span><strong>Áp dụng khi thanh toán</strong></div>
                            @else
                                <div class="discount-line"><span>Giảm giá</span><strong>-{{ money($discount) }}</strong></div>
                            @endif
                        </div>
                        <div class="summary-total"><span>Tạm tính sau giảm giá</span><strong>{{ money(max(0, $subtotal - $discount)) }}</strong></div>
                        <a class="btn btn-primary checkout-button" href="{{ route('checkout.index') }}">Tiến hành thanh toán <x-icon name="arrow-right" /></a>
                        <p class="secure-note"><x-icon name="shield" /> Thanh toán an toàn và bảo mật</p>
                    </aside>
                </div>
            </div>
        @endif
    </main>
@endsection
