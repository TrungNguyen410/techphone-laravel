@extends('layouts.admin')

@section('title', 'Tổng quan')

@php
    $hour = now()->hour;
    $greeting = $hour < 11 ? 'Chào buổi sáng' : ($hour < 14 ? 'Chào buổi trưa' : ($hour < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));
    $cards = [
        ['Tổng sản phẩm', $stats['products'], 'Đang được quản lý', 'smartphone', 'blue'],
        ['Tổng đơn hàng', $stats['orders'], 'Đơn hàng trong hệ thống', 'package', 'violet'],
        ['Khách hàng', $stats['customers'], 'Tài khoản khách hàng', 'users', 'green'],
        ['Tổng doanh thu', money($stats['revenue']), 'Đơn đã giao/hoàn thành năm '.$year, 'dollar-sign', 'orange'],
    ];
@endphp

@section('content')
    <div class="dashboard-welcome">
        <div><span>Cập nhật hôm nay</span><h2>{{ $greeting }}, {{ auth()->user()->full_name }}</h2><p>Đây là tình hình hoạt động mới nhất của cửa hàng.</p></div>
        <div class="dashboard-welcome-actions">
            <form method="GET" class="inline-form">
                <select name="year" data-auto-submit aria-label="Năm thống kê" class="dashboard-year">
                    @foreach (range(now()->year, now()->year - 4) as $option)
                        <option value="{{ $option }}" @selected($option === $year)>Năm {{ $option }}</option>
                    @endforeach
                </select>
            </form>
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
    <section class="chart-grid">
        <div class="admin-card chart-card">
            <div class="card-heading"><div><h2>Doanh thu theo tháng</h2><span>Đơn vị: triệu đồng · năm {{ $year }}</span></div></div>
            <canvas id="revenue-chart" data-values='@json($monthlyRevenue)' aria-label="Biểu đồ doanh thu theo tháng" role="img"></canvas>
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
        <div class="admin-card top-products">
            <div class="card-heading"><div><h2>Sản phẩm bán chạy</h2><span>Top theo số lượng bán</span></div></div>
            @foreach ($topProducts as $product)
                <div>
                    <b>{{ $loop->iteration }}</b>
                    <img src="{{ media_url($product->image) }}" alt="">
                    <span><strong>{{ $product->name }}</strong><small>Đã bán {{ $product->sold }}</small></span>
                    <strong>{{ money($product->price) }}</strong>
                </div>
            @endforeach
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/admin-dashboard.js') }}?v={{ filemtime(public_path('js/admin-dashboard.js')) }}"></script>
@endpush
