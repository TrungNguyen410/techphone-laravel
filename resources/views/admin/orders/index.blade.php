@extends('layouts.admin')

@section('title', 'Quản lý đơn hàng')

@section('content')
    <form class="admin-page-toolbar" method="GET" action="{{ route('admin.orders.index') }}">
        <div class="admin-search"><x-icon name="search" /><input name="search" maxlength="100" value="{{ request('search') }}" placeholder="Tìm mã đơn, khách hàng, số điện thoại..."></div>
        <select name="status" aria-label="Lọc trạng thái đơn hàng" data-auto-submit>
            <option value="">Tất cả trạng thái</option>
            @foreach (App\Models\Order::STATUSES as $status => $meta)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ $meta['label'] }}</option>
            @endforeach
        </select>
    </form>

    <div class="admin-table-card">
        <div class="admin-table-title"><div><h2>Danh sách đơn hàng</h2><span>{{ $orders->total() }} đơn hàng</span></div></div>
        @if ($orders->isEmpty())
            @include('partials.empty-state', ['title' => 'Chưa có dữ liệu', 'description' => 'Hãy thay đổi bộ lọc.'])
        @else
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Sản phẩm</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><strong>{{ $order->order_number }}</strong></td>
                                <td><span>{{ $order->customer_name }}<small class="table-subtext">{{ $order->customer_phone }}</small></span></td>
                                <td>{{ format_date($order->created_at, true) }}</td>
                                <td>{{ $order->itemCount() }} sản phẩm</td>
                                <td><strong>{{ money($order->total) }}</strong></td>
                                <td>
                                    <span class="payment-method-badge">{{ $order->paymentMethodLabel() }}</span>
                                    <small class="table-subtext">{{ $order->paymentStatusLabel() }}</small>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="inline-form">
                                        @csrf @method('PATCH')
                                        <select name="status" class="status-select {{ $order->statusClass() }}" aria-label="Trạng thái đơn {{ $order->order_number }}"
                                                data-auto-submit @disabled($order->nextStatuses() === [])
                                                data-confirm-cancel="Hủy đơn {{ $order->order_number }}? Tồn kho và lượt dùng voucher sẽ được hoàn lại.">
                                            @foreach ([$order->status, ...$order->nextStatuses()] as $status)
                                                <option value="{{ $status }}" @selected($status === $order->status)>{{ App\Models\Order::statusLabelOf($status) }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td><a class="table-view-button" href="{{ route('admin.orders.index', array_merge(request()->except('view'), ['view' => $order->id])) }}" aria-label="Xem đơn {{ $order->order_number }}"><x-icon name="eye" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->links() }}
        @endif
    </div>

    @if ($selected)
        @php $closeUrl = route('admin.orders.index', request()->except('view')); @endphp
        <dialog class="accessible-dialog order-detail-modal admin-order-modal" aria-label="Chi tiết đơn {{ $selected->order_number }}" data-autoshow data-close-url="{{ $closeUrl }}">
            <a class="icon-button modal-close" href="{{ $closeUrl }}" aria-label="Đóng chi tiết đơn">×</a>
            <span class="eyebrow">Chi tiết đơn hàng</span>
            <h2>{{ $selected->order_number }} <span class="status-badge {{ $selected->statusClass() }}">{{ $selected->statusLabel() }}</span></h2>
            <div class="admin-order-customer">
                <div><small>Khách hàng</small><strong>{{ $selected->customer_name }}</strong><span>{{ $selected->customer_phone }} · {{ $selected->customer_email }}</span></div>
                <div><small>Địa chỉ giao hàng</small><strong>{{ $selected->fullAddress() }}</strong></div>
                <div><small>Phương thức thanh toán</small><strong>{{ $selected->paymentMethodLabel() }}</strong><span>{{ $selected->paymentStatusLabel() }}</span></div>
            </div>
            @foreach ($selected->items as $item)
                <div class="modal-order-item">
                    <img src="{{ media_url($item->image) }}" alt="">
                    <span><strong>{{ $item->name }}</strong><small>{{ money($item->price) }} × {{ $item->quantity }}</small></span>
                    <b>{{ money($item->total) }}</b>
                </div>
            @endforeach
            <div class="summary-lines">
                <div><span>Tạm tính</span><strong>{{ money($selected->subtotal) }}</strong></div>
                <div><span>Phí vận chuyển</span><strong>{{ money($selected->shipping_fee) }}</strong></div>
                <div class="discount-line"><span>Giảm giá{{ $selected->voucher_code ? ' ('.$selected->voucher_code.')' : '' }}</span><strong>-{{ money($selected->discount) }}</strong></div>
            </div>
            @if ($selected->note)
                <p class="admin-order-note"><strong>Ghi chú:</strong> {!! nl2br(e($selected->note)) !!}</p>
            @endif

            @if ($selected->payment_confirmed_at)
                <section class="admin-payment-audit" aria-label="Lịch sử đối soát thanh toán">
                    <h3>Lịch sử đối soát thanh toán</h3>
                    <div><small>Mã tham chiếu</small><strong>{{ $selected->payment_reference ?: '—' }}</strong></div>
                    <div><small>Người xác nhận</small><strong>{{ $selected->paymentConfirmer?->full_name ?? '—' }}</strong></div>
                    <div><small>Thời gian</small><strong>{{ format_date($selected->payment_confirmed_at, true) }}</strong></div>
                    <div><small>Ghi chú</small><strong>{{ $selected->payment_note ?: '—' }}</strong></div>
                </section>
            @endif

            @if ($selected->transactions->isNotEmpty())
                <section class="admin-payment-audit" aria-label="Giao dịch VNPay">
                    <h3>Giao dịch VNPay</h3>
                    @foreach ($selected->transactions as $transaction)
                        <div><small>{{ $transaction->reference }}</small><strong>{{ $transaction->status }} · {{ money($transaction->amount) }}{{ $transaction->bank_code ? ' · '.$transaction->bank_code : '' }}</strong></div>
                    @endforeach
                </section>
            @endif

            @if (in_array($selected->payment_method, ['bank', 'momo'], true) && in_array($selected->payment_status, ['pending', 'failed'], true))
                <form class="admin-payment-reconciliation" method="POST" action="{{ route('admin.orders.payment', $selected) }}" aria-label="Đối soát thanh toán">
                    @csrf @method('PUT')
                    <h3>Đối soát thanh toán</h3>
                    <div class="form-grid">
                        <label class="form-field full"><span>Mã tham chiếu thanh toán</span><input name="reference" maxlength="150" value="{{ old('reference', $selected->payment_reference) }}"></label>
                        <label class="form-field full"><span>Ghi chú đối soát</span><textarea name="note" maxlength="1000">{{ old('note') }}</textarea></label>
                    </div>
                    <div class="admin-payment-actions">
                        <button class="btn btn-primary" name="payment_status" value="paid">Xác nhận đã thanh toán</button>
                        <button class="btn btn-outline" name="payment_status" value="failed">Đánh dấu thất bại</button>
                    </div>
                </form>
            @endif

            <form class="admin-tracking-form" method="POST" action="{{ route('admin.orders.shipping', $selected) }}">
                @csrf @method('PUT')
                <h3><x-icon name="truck" /> Thông tin vận chuyển</h3>
                <div class="form-grid">
                    <label class="form-field"><span>Đơn vị vận chuyển</span><input name="shipping_provider" value="{{ old('shipping_provider', $selected->shipping_provider) }}"></label>
                    <label class="form-field"><span>Mã vận đơn</span><input name="tracking_number" value="{{ old('tracking_number', $selected->tracking_number) }}"></label>
                    <label class="form-field full"><span>Ngày giao dự kiến</span><input type="date" name="estimated_delivery" value="{{ old('estimated_delivery', $selected->estimated_delivery?->format('Y-m-d')) }}"></label>
                </div>
                <button class="btn btn-primary">Lưu vận đơn</button>
            </form>
            <div class="success-total"><span>Tổng thanh toán</span><strong>{{ money($selected->total) }}</strong></div>

            @if (in_array($selected->status, ['pending', 'confirmed', 'cancelled', 'completed'], true))
                <form method="POST" action="{{ route('admin.orders.destroy', $selected) }}" class="admin-archive-form"
                      data-confirm="Đơn chưa giao sẽ được hủy (hoàn kho, trả voucher) rồi chuyển vào lưu trữ." data-confirm-title="Lưu trữ đơn hàng?">
                    @csrf @method('DELETE')
                    <button class="text-danger-button"><x-icon name="trash-2" /> Lưu trữ đơn hàng</button>
                </form>
            @endif
        </dialog>
    @endif
@endsection
