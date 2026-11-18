@extends('layouts.admin')

@section('title', 'Tổng quan')

@php
    $hour = now()->hour;
    $greeting = $hour < 11 ? 'Chào buổi sáng' : ($hour < 14 ? 'Chào buổi trưa' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));
    $cards = [
        ['Tổng sản phẩm', $stats['products'], 'Đang được quản lý', 'smartphone', 'blue'],
        ['Tổng đơn hàng', $stats['orders'], 'Đơn hàng trong hệ thống', 'package', 'violet'],
        ['Khách hàng', $stats['customers'], 'Tài khoản khách hàng', 'users', 'green'],
        ['Doanh thu năm '.$year, money($stats['revenue']), 'Đơn đã giao / hoàn thành', 'dollar-sign', 'orange'],
    ];
    $today = now();
    $dailyCount = count($revenue['daily']);
    $monthCount = count($revenue['monthly']);
    $charts = [
        [
            'key' => 'yearly',
            'title' => 'Theo năm',
            'subtitle' => $revenue['yearly'][0]['year'].' – '.$year,
            'labels' => array_map(fn ($row) => (string) $row['year'], $revenue['yearly']),
            'tips' => array_map(fn ($row) => 'Năm '.$row['year'], $revenue['yearly']),
            'values' => array_column($revenue['yearly'], 'total'),
            'unit' => 'Năm',
        ],
        [
            'key' => 'monthly',
            'title' => 'Theo tháng',
            'subtitle' => match (true) {
                $monthCount === 12 => '12 tháng năm '.$year,
                $monthCount === 0 => 'Năm '.$year.' chưa bắt đầu',
                default => 'Tháng 1 – '.$monthCount.' năm '.$year.' (đến tháng hiện tại)',
            },
            'labels' => array_map(fn ($m) => 'T'.$m, $monthCount ? range(1, $monthCount) : []),
            'tips' => array_map(fn ($m) => 'Tháng '.$m.'/'.$year, $monthCount ? range(1, $monthCount) : []),
            'values' => $revenue['monthly'],
            'unit' => 'Tháng',
        ],
        [
            'key' => 'daily',
            'title' => 'Theo ngày',
            'subtitle' => $revenue['isCurrentMonth']
                ? 'Từ 1/'.$month.' đến hôm nay '.$today->day.'/'.$month.'/'.$year
                : ($dailyCount ? 'Tháng '.$month.'/'.$year : 'Tháng '.$month.'/'.$year.' chưa bắt đầu'),
            'labels' => array_map(fn ($d) => (string) $d, $dailyCount ? range(1, $dailyCount) : []),
            'tips' => array_map(fn ($d) => 'Ngày '.$d.'/'.$month.'/'.$year, $dailyCount ? range(1, $dailyCount) : []),
            'values' => $revenue['daily'],
            'unit' => 'Ngày',
        ],
    ];
    $periodQuery = ['year' => $year, 'month' => $month];
    $catalogRoute = fn ($item) => $item->itemType() === 'accessory' ? 'admin.accessories.index' : 'admin.products.index';
    $typeLabel = fn ($item) => $item->itemType() === 'accessory' ? 'Phụ kiện' : 'Điện thoại';
@endphp

@section('content')
    <div class="dashboard-welcome">
        <div><span>Cập nhật hôm nay</span><h2>{{ $greeting }}, {{ auth()->user()->full_name }}</h2><p>Đây là tình hình hoạt động mới nhất của cửa hàng.</p></div>
        <div class="dashboard-welcome-actions">
            <a class="btn btn-primary" href="{{ route('admin.products.index') }}">Quản lý sản phẩm</a>
        </div>
    </div>
    <section class="stat-grid">
        @foreach ($cards as [$label, $value, $change, $icon, $color])
            <article class="stat-card">
                <div class="stat-icon {{ $color }}"><x-icon :name="$icon" /></div>
                <div><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $change }}</small></div>
            </article>
        @endforeach
    </section>

    @if ($lowStock['total'] > 0)
        <a class="low-stock-alert" href="#low-stock">
            <x-icon name="alert-triangle" />
            <span>
                <strong>Cảnh báo tồn kho:</strong> {{ $lowStock['total'] }} mặt hàng còn từ {{ $lowStock['threshold'] }} sản phẩm trở xuống{{ $lowStock['outOfStock'] ? ', trong đó '.$lowStock['outOfStock'].' mặt hàng đã hết' : '' }}.
            </span>
            <b>Xem danh sách</b>
        </a>
    @endif

    <section class="revenue-section" aria-labelledby="revenue-heading">
        <form class="revenue-filters" method="GET" action="{{ route('admin.dashboard') }}">
            <h2 id="revenue-heading">Doanh thu</h2>
            <label><span>Năm</span>
                <select name="year" data-auto-submit>
                    @foreach (range(now()->year, now()->year - 9) as $option)
                        <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label><span>Tháng</span>
                <select name="month" data-auto-submit>
                    @foreach (range(1, 12) as $option)
                        <option value="{{ $option }}" @selected($option === $month)>Tháng {{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <noscript><button class="btn btn-light">Xem</button></noscript>
            <small>Chỉ tính đơn đã giao hoặc hoàn thành, theo giờ Việt Nam</small>
        </form>
        <div class="revenue-grid">
            @foreach ($charts as $chart)
                @php
                    $total = array_sum($chart['values']);
                    $best = $total > 0 ? array_search(max($chart['values']), $chart['values'], true) : null;
                @endphp
                <div class="admin-card chart-card revenue-chart">
                    <div class="card-heading">
                        <div><h2>{{ $chart['title'] }}</h2><span>{{ $chart['subtitle'] }}</span></div>
                        <div class="revenue-total"><small>Tổng</small><strong>{{ money($total) }}</strong></div>
                    </div>
                    <div class="revenue-canvas">
                        <canvas data-revenue-chart="{{ $chart['key'] }}" data-labels='@json($chart['labels'])' data-tips='@json($chart['tips'])'
                                data-values='@json($chart['values'])' role="img" aria-label="{{ $chart['title'] }}: tổng {{ money($total) }}"></canvas>
                        @if ($total === 0)
                            <p class="revenue-empty">{{ $chart['values'] ? 'Chưa có doanh thu trong giai đoạn này' : 'Chưa có dữ liệu cho tháng này' }}</p>
                        @endif
                    </div>
                    <details class="revenue-table">
                        <summary>Xem bảng số liệu{{ $best !== null ? ' · cao nhất: '.$chart['tips'][$best] : '' }}</summary>
                        <table>
                            <thead><tr><th>{{ $chart['unit'] }}</th><th>Doanh thu</th></tr></thead>
                            <tbody>
                                @forelse (array_filter($chart['values']) as $index => $value)
                                    <tr><td>{{ $chart['tips'][$index] }}</td><td>{{ money($value) }}</td></tr>
                                @empty
                                    <tr><td colspan="2">Chưa có doanh thu trong giai đoạn này</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </details>
                </div>
            @endforeach
        </div>
    </section>

    <section class="chart-grid">
        <div class="admin-table-card top-customers">
            <div class="admin-table-title">
                <div><h2>Top 5 khách hàng mua nhiều nhất</h2><span>Theo tổng chi tiêu đơn đã giao/hoàn thành · tặng voucher riêng để tri ân</span></div>
                <a href="{{ route('admin.customers.index') }}">Tất cả khách hàng</a>
            </div>
            @if ($topCustomers->isEmpty())
                <p class="dashboard-empty">Chưa có khách hàng nào có đơn đã giao.</p>
            @else
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Khách hàng</th><th>Đơn đã giao</th><th>Tổng chi tiêu</th><th>Mua gần nhất</th><th class="table-actions-cell">Ưu đãi</th></tr></thead>
                        <tbody>
                            @foreach ($topCustomers as $customer)
                                <tr>
                                    <td><b class="rank-badge">{{ $loop->iteration }}</b></td>
                                    <td><span>{{ $customer->full_name }}<small class="table-subtext">{{ $customer->phone ?: $customer->email }}</small></span></td>
                                    <td>{{ $customer->order_count }}</td>
                                    <td><strong>{{ money($customer->total_spent) }}</strong></td>
                                    <td>{{ format_date($customer->last_order_at) }}</td>
                                    <td class="table-actions-cell">
                                        <a class="gift-button" href="{{ route('admin.dashboard', $periodQuery + ['gift' => $customer->id]) }}"><x-icon name="gift" /> Tặng voucher</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="admin-card chart-card">
            <div class="card-heading"><div><h2>Trạng thái đơn hàng</h2><span>Phân bổ hiện tại</span></div></div>
            <canvas id="status-chart" data-labels='@json(array_column($orderStatus, 'label'))' data-values='@json(array_column($orderStatus, 'count'))' aria-label="Biểu đồ trạng thái đơn hàng" role="img"></canvas>
        </div>
    </section>

    <section class="dashboard-bottom-grid">
        <div class="admin-table-card">
            <div class="admin-table-title"><div><h2>Đơn hàng gần đây</h2><span>Các đơn mới nhất</span></div><a href="{{ route('admin.orders.index') }}">Xem tất cả</a></div>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                        @foreach ($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.index', ['view' => $order->id]) }}"><strong>{{ $order->order_number }}</strong></a></td>
                                <td><span>{{ $order->customer_name }}<small class="table-subtext">{{ $order->customer_phone }}</small></span></td>
                                <td>{{ format_date($order->created_at) }}</td>
                                <td><strong>{{ money($order->total) }}</strong></td>
                                <td><span class="status-badge {{ $order->statusClass() }}">{{ $order->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="dashboard-side-stack">
            <div class="admin-card top-products">
                <div class="card-heading"><div><h2>Sản phẩm bán chạy</h2><span>Điện thoại và phụ kiện, theo số lượng đã bán</span></div></div>
                @forelse ($topProducts as $product)
                    <div>
                        <b>{{ $loop->iteration }}</b>
                        <img src="{{ media_url($product->image) }}" alt="">
                        <span><strong>{{ $product->name }}</strong><small>{{ $typeLabel($product) }} · đã bán {{ $product->sold }}</small></span>
                        <strong>{{ money($product->price) }}</strong>
                    </div>
                @empty
                    <p class="dashboard-empty">Chưa có sản phẩm nào được bán.</p>
                @endforelse
            </div>
            <div class="admin-card low-stock-card" id="low-stock">
                <div class="card-heading">
                    <div><h2>Sắp hết hàng</h2><span>Đang bán, còn từ {{ $lowStock['threshold'] }} sản phẩm trở xuống</span></div>
                    @if ($lowStock['total'] > 0)<b class="low-stock-count">{{ $lowStock['total'] }}</b>@endif
                </div>
                @if ($lowStock['items']->isEmpty())
                    <p class="dashboard-empty">Tất cả mặt hàng đang bán đều còn trên {{ $lowStock['threshold'] }} sản phẩm.</p>
                @else
                    <ul>
                        @foreach ($lowStock['items'] as $item)
                            <li>
                                <img src="{{ media_url($item->image) }}" alt="">
                                <a href="{{ route($catalogRoute($item), ['edit' => $item->id]) }}" title="Nhập thêm {{ $item->name }}">
                                    <strong>{{ $item->name }}</strong><small>{{ $typeLabel($item) }} · bấm để nhập hàng</small>
                                </a>
                                <span @class(['stock-pill', 'is-out' => $item->stock === 0, 'is-low' => $item->stock > 0])>
                                    <x-icon name="alert-triangle" />{{ $item->stock === 0 ? 'Hết hàng' : 'Còn '.$item->stock }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($lowStock['total'] > $lowStock['items']->count())
                    <small class="low-stock-more">Và {{ $lowStock['total'] - $lowStock['items']->count() }} mặt hàng khác</small>
                @endif
            </div>
        </div>
    </section>

    @if ($giftCustomer)
        @php $closeUrl = route('admin.dashboard', $periodQuery); @endphp
        <dialog class="accessible-dialog order-detail-modal gift-voucher-modal" aria-label="Tặng voucher cho {{ $giftCustomer->full_name }}" data-autoshow data-close-url="{{ $closeUrl }}">
            <a class="icon-button modal-close" href="{{ $closeUrl }}" aria-label="Đóng">×</a>
            <h2><x-icon name="gift" /> Tặng voucher</h2>
            <p class="gift-voucher-customer">
                <strong>{{ $giftCustomer->full_name }}</strong> · {{ $giftCustomer->phone ?: $giftCustomer->email }}
                @isset($giftCustomer->total_spent)
                    · đã chi {{ money($giftCustomer->total_spent) }} qua {{ $giftCustomer->order_count }} đơn
                @endisset
            </p>
            <form method="POST" action="{{ route('admin.customers.vouchers.store', $giftCustomer) }}" data-gift-form>
                @csrf
                <input type="hidden" name="return_to" value="{{ $closeUrl }}">
                <div class="form-grid">
                    <label class="form-field">
                        <span>Loại ưu đãi</span>
                        <select name="type" data-gift-type>
                            @foreach (['percent' => 'Giảm theo %', 'fixed' => 'Giảm số tiền', 'shipping' => 'Giảm phí vận chuyển'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', 'percent') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span data-gift-value-label>Giá trị giảm ({{ old('type', 'percent') === 'percent' ? '%' : 'đ' }}) *</span>
                        <input type="number" name="value" min="1" required value="{{ old('value', 10) }}" @error('value') aria-invalid="true" @enderror>
                        @error('value')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field" data-gift-max @if (old('type', 'percent') !== 'percent') hidden @endif>
                        <span>Giảm tối đa (đ, 0 = không giới hạn)</span>
                        <input type="number" name="max_discount" min="0" step="1000" value="{{ old('max_discount', 500000) }}">
                        @error('max_discount')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Đơn tối thiểu (đ)</span>
                        <input type="number" name="min_order" min="0" step="1000" value="{{ old('min_order', 0) }}">
                        @error('min_order')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Hạn dùng *</span>
                        <input type="date" name="end_date" min="{{ now()->toDateString() }}" required value="{{ old('end_date', now()->addDays(30)->toDateString()) }}" @error('end_date') aria-invalid="true" @enderror>
                        @error('end_date')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field full">
                        <span>Lời nhắn cho khách</span>
                        <input name="note" maxlength="120" value="{{ old('note') }}" placeholder="Tri ân khách hàng {{ $giftCustomer->full_name }}">
                    </label>
                </div>
                <p class="gift-voucher-hint"><x-icon name="check-circle" /> Mã dùng 1 lần, chỉ khách này nhìn thấy (Tài khoản → Voucher của tôi) và áp dụng được.</p>
                <div class="modal-actions">
                    <a class="btn btn-light" href="{{ $closeUrl }}">Hủy</a>
                    <button class="btn btn-primary"><x-icon name="gift" /> Tạo và tặng voucher</button>
                </div>
            </form>
        </dialog>
    @endif

    @if ($giftedVoucher)
        <dialog class="accessible-dialog order-detail-modal gift-voucher-modal" aria-label="Đã tặng voucher" data-autoshow>
            <button type="button" class="icon-button modal-close" aria-label="Đóng" data-close-dialog>×</button>
            <div class="gift-voucher-done">
                <x-icon name="check-circle" />
                <span>Đã thêm mã vào mục <b>Voucher của tôi</b> trong tài khoản của <b>{{ $giftedVoucher->assignedUser?->full_name }}</b>. Mỗi mã dùng 1 lần.</span>
                <div class="gift-voucher-code">
                    <strong>{{ $giftedVoucher->code }}</strong>
                    <button type="button" class="btn btn-light" data-copy="{{ $giftedVoucher->code }}"><x-icon name="copy" /> Sao chép</button>
                </div>
                <small>{{ $giftedVoucher->describe() }} · hạn đến {{ format_date($giftedVoucher->end_date) }}</small>
                <button type="button" class="btn btn-primary" data-close-dialog>Xong</button>
            </div>
        </dialog>
    @endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/admin-dashboard.js') }}?v={{ filemtime(public_path('js/admin-dashboard.js')) }}"></script>
@endpush
