@extends('layouts.store')

@section('title', 'Thanh toán')

@php
    $hints = [
        'cod' => 'Thanh toán trực tiếp cho nhân viên giao hàng',
        'bank' => $providers['bank']['autoConfirm'] ?? false
            ? 'Đặt hàng rồi quét mã VietQR theo mã đơn; thanh toán được xác nhận tự động'
            : 'Đặt hàng rồi quét mã VietQR với nội dung là mã đơn; nhân viên sẽ đối soát',
        'momo' => 'Quét mã MoMo và xác nhận thanh toán trước khi đặt hàng',
        'card' => 'Thanh toán an toàn trên cổng VNPay; '.$store['storeName'].' không thu thập thông tin thẻ',
    ];
    $selectedMethod = old('payment_method', 'cod');
    $momo = $providers['momo']['display'];
    $field = fn ($name, $fallback = '') => old($name, $fallback);
@endphp

@section('content')
    <main class="page-shell checkout-page">
        @if ($lines->isEmpty())
            <div class="container narrow-page">
                @include('partials.empty-state', ['title' => 'Không có sản phẩm để thanh toán', 'actionLabel' => 'Tiếp tục mua sắm', 'actionUrl' => route('products.index')])
            </div>
        @else
            <div class="container">
                <div class="page-hero small">
                    <div><span>Chỉ còn một bước</span><h1>Hoàn tất đơn hàng</h1></div>
                    <p>Kiểm tra thông tin trước khi xác nhận đặt hàng.</p>
                </div>
                <form class="checkout-layout" method="POST" action="{{ route('checkout.store') }}" novalidate data-checkout
                      data-subtotal="{{ $subtotal }}" data-quotes='@json($quotes)' data-default-quote='@json($defaultQuote)'
                      data-momo='@json($momo)'>
                    @csrf
                    <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
                    <input type="hidden" name="transfer_content" value="" data-transfer-input>
                    <div class="checkout-main">
                        <section class="panel checkout-section">
                            <h2><x-icon name="user" /> Thông tin người nhận</h2>
                            @if ($useSavedAddress)
                                {{-- Máy chủ lấy lại địa chỉ đã lưu từ tài khoản; các ô ẩn chỉ để tính phí ship trên trình duyệt. --}}
                                <input type="hidden" name="address_mode" value="saved">
                                @foreach (['full_name', 'phone', 'email', 'address', 'ward'] as $name)
                                    <input type="hidden" name="{{ $name }}" value="{{ $savedAddress[$name] }}">
                                @endforeach
                                <input type="hidden" name="province" value="{{ $savedAddress['province'] }}" data-province>
                                <div class="saved-address-card">
                                    <x-icon name="map-pin" />
                                    <div>
                                        <small>Giao đến địa chỉ đã lưu</small>
                                        <strong>{{ $savedAddress['full_name'] }} · {{ $savedAddress['phone'] }}</strong>
                                        <p>{{ \App\Support\ShippingAddress::format($savedAddress) }}</p>
                                        <span>{{ $savedAddress['email'] }}</span>
                                    </div>
                                    <a class="btn btn-light" href="{{ route('checkout.index', ['address' => 'new']) }}"><x-icon name="edit-2" /> Giao đến địa chỉ khác</a>
                                </div>
                            @else
                                <input type="hidden" name="address_mode" value="new">
                                @if ($savedAddress)
                                    <div class="address-switch">
                                        <span>Đơn này giao đến địa chỉ mới</span>
                                        <a href="{{ route('checkout.index') }}">Dùng địa chỉ đã lưu</a>
                                    </div>
                                @endif
                                @include('partials.shipping-address-fields', ['values' => $formDefaults, 'prefix' => 'checkout', 'provinces' => $provinces])
                                @if ($savedAddress)
                                    <label class="save-address-option">
                                        <input type="checkbox" name="save_address" value="1" @checked(old('save_address'))>
                                        <span>Lưu làm địa chỉ mặc định cho lần mua sau</span>
                                    </label>
                                @else
                                    <p class="checkout-address-note"><x-icon name="check-circle" /> Địa chỉ này sẽ được lưu vào tài khoản để lần sau không phải nhập lại</p>
                                @endif
                            @endif
                            <div class="form-grid checkout-note-grid">
                                <label class="form-field full">
                                    <span>Ghi chú đơn hàng</span>
                                    <textarea rows="3" name="note" placeholder="Ví dụ: giao giờ hành chính">{{ $field('note') }}</textarea>
                                </label>
                            </div>
                        </section>

                        <section class="panel checkout-section">
                            <h2><x-icon name="credit-card" /> Phương thức thanh toán</h2>
                            <div class="payment-options">
                                @foreach (App\Models\Order::PAYMENT_METHODS as $method => $label)
                                    @continue(! $providers[$method]['enabled'])
                                    <label @class(['active' => $selectedMethod === $method]) data-payment-option>
                                        <input type="radio" name="payment_method" value="{{ $method }}" @checked($selectedMethod === $method)>
                                        <span><strong>{{ $label }}</strong><small>{{ $hints[$method] }}</small></span>
                                    </label>
                                @endforeach
                            </div>
                        </section>

                        @if ($providers['momo']['enabled'])
                            <section class="panel checkout-section bank-transfer-section" data-payment-confirm="momo" hidden>
                                <h2><x-icon name="credit-card" /> Thanh toán qua MoMo</h2>
                                <div class="bank-transfer-grid">
                                    <div class="bank-qr-card"><img alt="Mã QR thanh toán MoMo" data-qr="momo"></div>
                                    <div class="bank-transfer-info">
                                        <span>Quét mã trong ứng dụng MoMo</span>
                                        <div><small>Số điện thoại nhận</small><strong>{{ $momo['phone'] }}</strong></div>
                                        <div><small>Chủ tài khoản</small><strong>{{ $momo['account_name'] }}</strong></div>
                                        <div><small>Số tiền</small><strong data-order-total>{{ money($subtotal + $defaultQuote['fee'] - $defaultQuote['discount']) }}</strong></div>
                                        <div><small>Nội dung</small><strong data-transfer-content></strong></div>
                                    </div>
                                </div>
                                <p class="bank-transfer-note">Sau khi thanh toán qua MoMo, bấm <strong>Tôi đã thanh toán</strong> để hoàn tất đơn hàng.</p>
                            </section>
                        @endif
                    </div>

                    <aside class="panel checkout-order">
                        <h2><x-icon name="package" /> Đơn hàng ({{ $count }})</h2>
                        <div class="checkout-items">
                            @foreach ($lines as $line)
                                <div>
                                    <img src="{{ media_url($line->item->image) }}" alt="{{ $line->item->name }}">
                                    <span><strong>{{ $line->item->name }}</strong><small>Số lượng: {{ $line->quantity }}</small></span>
                                    <b>{{ money($line->lineTotal()) }}</b>
                                </div>
                            @endforeach
                        </div>
                        <div class="summary-lines">
                            <div><span>Tạm tính</span><strong>{{ money($subtotal) }}</strong></div>
                            <div><span>Phí vận chuyển</span><strong data-shipping-fee>{{ $defaultQuote['fee'] ? money($defaultQuote['fee']) : 'Miễn phí' }}</strong></div>
                            <div><span>Dự kiến giao</span><strong data-shipping-eta>{{ $defaultQuote['eta'] }}</strong></div>
                            <div class="discount-line"><span>Giảm giá{{ $voucher ? ' ('.$voucher->code.')' : '' }}</span><strong data-discount>-{{ money($defaultQuote['discount']) }}</strong></div>
                        </div>
                        <div class="summary-total"><span>Tổng thanh toán</span><strong data-order-total>{{ money($subtotal + $defaultQuote['fee'] - $defaultQuote['discount']) }}</strong></div>
                        <button class="btn btn-primary checkout-button" data-checkout-submit data-payment-reference="{{ $paymentReference }}">Xác nhận đặt hàng</button>
                        <button type="button" class="bank-edit-button" data-payment-back hidden>Quay lại sửa thông tin</button>
                        <p class="checkout-address-note" data-payment-pending-note hidden>
                            <x-icon name="check-circle" /> Chưa tạo đơn hàng cho tới khi bạn xác nhận đã thanh toán
                        </p>
                        <p class="checkout-address-note"><x-icon name="map-pin" /> {{ $useSavedAddress ? 'Giao đến địa chỉ đã lưu trong tài khoản' : 'Giao đến địa chỉ bạn đã cung cấp' }}</p>
                    </aside>
                </form>
            </div>
        @endif
    </main>
@endsection
