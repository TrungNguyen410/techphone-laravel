<div class="voucher-box">
    <div class="voucher-title"><x-icon name="tag" /> Mã giảm giá</div>
    @if ($voucher)
        <div class="applied-voucher">
            <div><strong>{{ $voucher->code }}</strong><span>Đã áp dụng cho đơn hàng</span></div>
            <form method="POST" action="{{ route('cart.voucher.remove') }}" class="inline-form">
                @csrf @method('DELETE')
                <button aria-label="Bỏ mã giảm giá"><x-icon name="x" /></button>
            </form>
        </div>
    @else
        <form method="POST" action="{{ route('cart.voucher.apply') }}">
            @csrf
            <input name="code" value="{{ old('code') }}" placeholder="Nhập mã ưu đãi" data-uppercase>
            <button>Áp dụng</button>
        </form>
    @endif
    <small>Thử: TECH10, GIAM200K hoặc FREESHIP</small>
</div>
