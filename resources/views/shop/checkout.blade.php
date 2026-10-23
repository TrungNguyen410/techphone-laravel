@extends('layouts.store')

@section('title', 'Thanh toán')

@php
    $hints = [
        'cod' => 'Thanh toán trực tiếp cho nhân viên giao hàng',
        'bank' => 'Quét mã QR và xác nhận đã chuyển khoản trước khi đặt hàng',
        'momo' => 'Quét mã MoMo và xác nhận thanh toán trước khi đặt hàng',
        'card' => 'Thanh toán an toàn trên cổng VNPay; '.$store['storeName'].' không thu thập thông tin thẻ',
    ];
    $selectedMethod = old('payment_method', 'cod');
    $bank = $providers['bank']['display'];
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
                      data-bank='@json($bank)' data-momo='@json($momo)'>
                    @csrf
                    <input type="hidden" name="checkout_key" value="{{ $checkoutKey }}">
                    <input type="hidden" name="transfer_content" value="" data-transfer-input>
                    <div class="checkout-main">
                        <section class="panel checkout-section">
                            <h2><x-icon name="user" /> Thông tin người nhận</h2>
                            <div class="form-grid">
                                @foreach ([
                                    ['full_name', 'Họ và tên *', 'text', $user->full_name, ''],
                                    ['phone', 'Số điện thoại *', 'tel', $user->phone, ''],
                                    ['email', 'Email *', 'email', $user->email, 'full'],
                                ] as [$name, $label, $type, $default, $width])
                                    <label class="form-field {{ $width }}">
                                        <span>{{ $label }}</span>
                                        <input type="{{ $type }}" name="{{ $name }}" value="{{ $field($name, $default) }}" @error($name) aria-invalid="true" @enderror data-checkout-phone="{{ $name === 'phone' ? '1' : '' }}">
                                        @error($name)<small>{{ $message }}</small>@enderror
                                    </label>
                                @endforeach
                                <label class="form-field full">
                                    <span>Địa chỉ nhận hàng *</span>
                                    <textarea rows="3" name="address" @error('address') aria-invalid="true" @enderror>{{ $field('address', $user->address) }}</textarea>
                                    @error('address')<small>{{ $message }}</small>@enderror
                                </label>
                                <label class="form-field full">
                                    <span>Tỉnh/thành phố *</span>
                                    <select name="province" data-province @error('province') aria-invalid="true" @enderror>
                                        <option value="">Chọn tỉnh/thành phố</option>
                                        @foreach ($provinces as $province)
                                            <option value="{{ $province }}" @selected($field('province') === $province)>{{ $province }}</option>
                                        @endforeach
                                    </select>
                                    @error('province')<small>{{ $message }}</small>@enderror
                                </label>
                                <label class="form-field">
                                    <span>Quận/huyện cũ (nếu có)</span>
                                    <input name="district" value="{{ $field('district') }}" placeholder="Ví dụ: Quận 1 (địa chỉ cũ)">
                                </label>
                                <label class="form-field">
                                    <span>Phường/xã/đặc khu *</span>
                                    <input name="ward" value="{{ $field('ward') }}" placeholder="Ví dụ: Bến Nghé" @error('ward') aria-invalid="true" @enderror>
                                    @error('ward')<small>{{ $message }}</small>@enderror
                                </label>
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

                        @if ($providers['bank']['enabled'])
                            <section class="panel checkout-section bank-transfer-section" data-payment-confirm="bank" hidden>
                                <h2><x-icon name="credit-card" /> Thanh toán chuyển khoản</h2>
                                <div class="bank-transfer-grid">
                                    <div class="bank-qr-card"><img alt="Mã QR chuyển khoản {{ $store['storeName'] }}" data-qr="bank"></div>
                                    <div class="bank-transfer-info">
                                        <span>Quét QR hoặc chuyển khoản thủ công</span>
                                        <h3>{{ $bank['name'] }}</h3>
                                        <div><small>Số tài khoản</small><strong>{{ $bank['account_number'] }}</strong></div>
                                        <div><small>Chủ tài khoản</small><strong>{{ $bank['account_name'] }}</strong></div>
                                        <div><small>Số tiền</small><strong data-order-total>{{ money($subtotal + $defaultQuote['fee'] - $defaultQuote['discount']) }}</strong></div>
                                        <div><small>Nội dung chuyển khoản</small><strong data-transfer-content></strong></div>
                                    </div>
                                </div>
                                <p class="bank-transfer-note">
                                    Sau khi chuyển khoản, bấm <strong>Tôi đã thanh toán</strong> để hoàn tất đơn hàng.
                                    Nhân viên {{ $store['storeName'] }} sẽ đối soát và xác nhận thanh toán.
                                </p>
                            </section>
                        @endif
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
                        <p class="checkout-address-note"><x-icon name="map-pin" /> Giao đến địa chỉ bạn đã cung cấp</p>
                    </aside>
                </form>
            </div>
        @endif
    </main>
@endsection
