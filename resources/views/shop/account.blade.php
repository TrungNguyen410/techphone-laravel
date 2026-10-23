@extends('layouts.store')

@section('title', 'Tài khoản của tôi')

@section('content')
    <main class="page-shell account-page">
        <div class="container">
            <div class="page-title-row"><div><span class="eyebrow">Khu vực thành viên</span><h1>Tài khoản của tôi</h1></div></div>
            <div class="account-layout">
                <aside class="account-sidebar panel">
                    <div class="account-user"><div>{{ mb_substr($user->full_name ?: $user->phone, 0, 1) }}</div><span><strong>{{ $user->full_name }}</strong><small>{{ $user->phone }}</small></span></div>
                    <a href="{{ route('account.index', ['tab' => 'profile']) }}" @class(['account-tab', 'active' => $tab === 'profile'])><x-icon name="user" /> Thông tin cá nhân</a>
                    <a href="{{ route('account.index', ['tab' => 'orders']) }}" @class(['account-tab', 'active' => $tab === 'orders'])><x-icon name="package" /> Đơn hàng của tôi</a>
                    <a href="{{ route('account.index', ['tab' => 'password']) }}" @class(['account-tab', 'active' => $tab === 'password'])><x-icon name="lock" /> Đổi mật khẩu</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout"><x-icon name="log-out" /> Đăng xuất</button>
                    </form>
                </aside>

                <section class="account-content panel">
                    @if ($tab === 'profile')
                        <form method="POST" action="{{ route('account.profile') }}">
                            @csrf @method('PUT')
                            <div class="content-heading"><h2>Thông tin cá nhân</h2><p>Cập nhật thông tin dùng cho đơn hàng và liên hệ.</p></div>
                            <div class="form-grid">
                                <label class="form-field">
                                    <span>Họ và tên *</span>
                                    <input name="full_name" value="{{ old('full_name', $user->full_name) }}" @error('full_name') aria-invalid="true" @enderror>
                                    @error('full_name')<small>{{ $message }}</small>@enderror
                                </label>
                                <label class="form-field"><span>Số điện thoại đăng nhập</span><input value="{{ $user->phone }}" readonly><small>Đã xác minh qua SMS</small></label>
                                <label class="form-field full"><span>Địa chỉ</span><textarea name="address" rows="3">{{ old('address', $user->address) }}</textarea></label>
                            </div>
                            <button class="btn btn-primary">Lưu thay đổi</button>
                        </form>
                    @elseif ($tab === 'orders')
                        <div class="content-heading"><h2>Đơn hàng của tôi</h2><p>Theo dõi trạng thái và xem lại lịch sử mua sắm.</p></div>
                        <section class="account-order-lookup">
                            <div class="content-heading"><h3>Tra cứu đơn hàng khác</h3><p>Nhập mã đơn và số điện thoại dùng khi đặt hàng.</p></div>
                            <form class="lookup-form panel" method="GET" action="{{ route('orders.lookup') }}">
                                <label><span>Mã đơn hàng</span><input name="order_number" placeholder="Ví dụ: TP260601" data-uppercase required></label>
                                <label><span>Số điện thoại</span><input name="phone" value="{{ $user->phone }}" required></label>
                                <button class="btn btn-primary"><x-icon name="search" /> Tra cứu</button>
                            </form>
                        </section>
                        @if ($orders->isEmpty())
                            @include('partials.empty-state', ['title' => 'Bạn chưa có đơn hàng', 'actionLabel' => 'Mua sắm ngay', 'actionUrl' => route('products.index')])
                        @else
                            <div class="account-orders">
                                @foreach ($orders as $order)
                                    @php $first = $order->items->first(); @endphp
                                    <article>
                                        <div class="order-card-head">
                                            <span><small>Mã đơn</small><strong>{{ $order->order_number }}</strong></span>
                                            <span><small>Ngày đặt</small><strong>{{ format_date($order->created_at) }}</strong></span>
                                            <span class="status-badge {{ $order->statusClass() }}">{{ $order->statusLabel() }}</span>
                                        </div>
                                        <div class="order-preview">
                                            <img src="{{ media_url($first?->image) }}" alt="">
                                            <div>
                                                <strong>{{ $first?->name }}</strong>
                                                <small>{{ $order->items->count() > 1 ? 'và '.($order->items->count() - 1).' sản phẩm khác' : $first?->quantity.' sản phẩm' }}</small>
                                            </div>
                                            <b>{{ money($order->total) }}</b>
                                        </div>
                                        @include('partials.order-shipping-note', ['order' => $order])
                                        <div class="order-card-actions">
                                            <a href="{{ route('account.index', ['tab' => 'orders', 'order' => $order->id]) }}"><x-icon name="eye" /> Chi tiết</a>
                                            <form method="POST" action="{{ route('orders.reorder', $order) }}" class="inline-form">
                                                @csrf
                                                <button><x-icon name="refresh-ccw" /> Đặt lại</button>
                                            </form>
                                            @if ($order->payment_method === 'card' && in_array($order->payment_status, ['pending', 'failed'], true) && $order->status !== 'cancelled')
                                                <form method="POST" action="{{ route('orders.pay', $order) }}" class="inline-form">
                                                    @csrf
                                                    <button><x-icon name="credit-card" /> Thanh toán</button>
                                                </form>
                                            @endif
                                            @if ($order->canBeCancelledByCustomer())
                                                <form method="POST" action="{{ route('orders.cancel', $order) }}" class="inline-form"
                                                      data-confirm="Đơn hàng sẽ được chuyển sang trạng thái đã hủy và không thể khôi phục." data-confirm-title="Hủy đơn hàng?">
                                                    @csrf
                                                    <button class="danger"><x-icon name="x-circle" /> Hủy đơn</button>
                                                </form>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <form method="POST" action="{{ route('account.password') }}">
                            @csrf @method('PUT')
                            <div class="content-heading"><h2>Đổi mật khẩu</h2><p>Sử dụng mật khẩu có ít nhất 6 ký tự để bảo vệ tài khoản.</p></div>
                            <div class="password-form">
                                <label class="form-field"><span>Mật khẩu hiện tại</span><input type="password" name="current_password" required autocomplete="current-password">@error('current_password')<small>{{ $message }}</small>@enderror</label>
                                <label class="form-field"><span>Mật khẩu mới</span><input type="password" name="password" required minlength="6" autocomplete="new-password">@error('password')<small>{{ $message }}</small>@enderror</label>
                                <label class="form-field"><span>Xác nhận mật khẩu mới</span><input type="password" name="password_confirmation" required autocomplete="new-password"></label>
                            </div>
                            <button class="btn btn-primary">Cập nhật mật khẩu</button>
                        </form>
                    @endif
                </section>
            </div>
        </div>

        @if ($selectedOrder)
            <dialog class="accessible-dialog order-detail-modal" aria-label="Chi tiết đơn {{ $selectedOrder->order_number }}" data-autoshow data-close-url="{{ route('account.index', ['tab' => 'orders']) }}">
                <a class="icon-button modal-close" href="{{ route('account.index', ['tab' => 'orders']) }}" aria-label="Đóng chi tiết đơn">×</a>
                <h2>Chi tiết đơn {{ $selectedOrder->order_number }}</h2>
                @foreach ($selectedOrder->items as $item)
                    <div class="modal-order-item">
                        <img src="{{ media_url($item->image) }}" alt="{{ $item->name }}">
                        <span><strong>{{ $item->name }}</strong><small>{{ money($item->price) }} × {{ $item->quantity }}</small></span>
                        <b>{{ money($item->total) }}</b>
                    </div>
                @endforeach
                <div class="summary-lines">
                    <div><span>Tạm tính</span><strong>{{ money($selectedOrder->subtotal) }}</strong></div>
                    <div><span>Phí vận chuyển</span><strong>{{ $selectedOrder->shipping_fee ? money($selectedOrder->shipping_fee) : 'Miễn phí' }}</strong></div>
                    <div class="discount-line"><span>Giảm giá</span><strong>-{{ money($selectedOrder->discount) }}</strong></div>
                    <div><span>Thanh toán</span><strong>{{ $selectedOrder->paymentMethodLabel() }} · {{ $selectedOrder->paymentStatusLabel() }}</strong></div>
                    <div><span>Giao đến</span><strong>{{ $selectedOrder->fullAddress() }}</strong></div>
                </div>
                @include('partials.order-shipping-note', ['order' => $selectedOrder])
                <div class="success-total"><span>Tổng thanh toán</span><strong>{{ money($selectedOrder->total) }}</strong></div>
            </dialog>
        @endif
    </main>
@endsection
