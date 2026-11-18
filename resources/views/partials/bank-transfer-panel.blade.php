{{--
    Mã VietQR cho đơn chuyển khoản: nội dung chuyển khoản là mã đơn. Khi đã cấu hình SePay, trang tự hỏi
    trạng thái mỗi 5 giây và báo "Đã nhận thanh toán" ngay khi tiền về.
--}}
@php
    $bank = app(\App\Services\Payments\PaymentService::class)->providers()['bank'];
@endphp
@if ($order->payment_method === 'bank' && $bank['enabled'])
    @if ($order->payment_status === 'paid')
        <section class="panel checkout-section bank-transfer-section bank-transfer-paid" role="status">
            <h2><x-icon name="check-circle" /> Đã nhận thanh toán</h2>
            <p class="bank-transfer-note">{{ $store['storeName'] }} đã nhận {{ money($order->total) }} cho đơn <strong>{{ $order->order_number }}</strong>. Đơn hàng đã được xác nhận.</p>
        </section>
    @elseif ($order->awaitsBankTransfer())
        @php
            $display = $bank['display'];
            $qr = 'https://img.vietqr.io/image/'.$display['bin'].'-'.$display['account_number'].'-compact2.png?'
                .http_build_query(['amount' => $order->total, 'addInfo' => $order->order_number, 'accountName' => $display['account_name']]);
        @endphp
        <section class="panel checkout-section bank-transfer-section"
                 @if ($bank['autoConfirm']) data-payment-poll="{{ route('orders.payment-status', $order) }}" @endif>
            <h2><x-icon name="credit-card" /> Thanh toán chuyển khoản</h2>
            <div class="bank-transfer-grid">
                <div class="bank-qr-card"><img src="{{ $qr }}" alt="Mã QR chuyển khoản đơn {{ $order->order_number }}" loading="lazy"></div>
                <div class="bank-transfer-info">
                    <span>Quét QR hoặc chuyển khoản thủ công</span>
                    <h3>{{ $display['name'] }}</h3>
                    <div><small>Số tài khoản</small><strong>{{ $display['account_number'] }}</strong></div>
                    <div><small>Chủ tài khoản</small><strong>{{ $display['account_name'] }}</strong></div>
                    <div><small>Số tiền</small><strong>{{ money($order->total) }}</strong></div>
                    <div>
                        <small>Nội dung chuyển khoản</small>
                        <strong>{{ $order->order_number }}</strong>
                        <button type="button" class="copy-inline" data-copy="{{ $order->order_number }}" data-copy-message="Đã sao chép nội dung chuyển khoản"><x-icon name="copy" /> Sao chép</button>
                    </div>
                </div>
            </div>
            <p class="bank-transfer-note" data-payment-poll-note>
                @if ($bank['autoConfirm'])
                    <x-icon name="loader" class="spin" /> Đang chờ thanh toán. Hệ thống sẽ <strong>tự động xác nhận</strong> trong vài giây sau khi nhận được tiền — vui lòng giữ đúng nội dung chuyển khoản là mã đơn.
                @else
                    Vui lòng ghi đúng nội dung chuyển khoản là mã đơn. Nhân viên {{ $store['storeName'] }} sẽ đối soát và xác nhận thanh toán.
                @endif
            </p>
        </section>
    @endif
@endif
