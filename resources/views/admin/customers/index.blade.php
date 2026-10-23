@extends('layouts.admin')

@section('title', 'Quản lý khách hàng')

@php
    $statusLabels = ['active' => 'Hoạt động', 'locked' => 'Đã khóa', 'inactive' => 'Ngừng hoạt động'];
@endphp

@section('content')
    <form class="admin-page-toolbar" method="GET" action="{{ route('admin.customers.index') }}">
        <div class="admin-search"><x-icon name="search" /><input name="search" maxlength="100" value="{{ request('search') }}" placeholder="Tìm khách hàng..."></div>
    </form>
    <div class="admin-table-card">
        <div class="admin-table-title"><div><h2>Danh sách khách hàng</h2><span>{{ $customers->total() }} tài khoản</span></div></div>
        @if ($customers->isEmpty())
            @include('partials.empty-state', ['title' => 'Chưa có dữ liệu', 'description' => 'Hãy thay đổi bộ lọc.'])
        @else
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Khách hàng</th><th>Số điện thoại</th><th>Ngày tham gia</th><th>Số đơn</th><th>Tổng chi tiêu</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td><div class="table-user"><div>{{ mb_substr($customer->full_name, 0, 1) }}</div><span><strong>{{ $customer->full_name }}</strong><small>{{ $customer->email }}</small></span></div></td>
                                <td>{{ $customer->phone }}</td>
                                <td>{{ format_date($customer->created_at) }}</td>
                                <td><strong>{{ $customer->orders_count }}</strong></td>
                                <td><strong>{{ money($customer->total_spent) }}</strong></td>
                                <td><span class="status-badge {{ $customer->status === 'active' ? 'status-completed' : 'status-cancelled' }}">{{ $statusLabels[$customer->status] }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.customers.lock', $customer) }}" class="inline-form"
                                          @if ($customer->status === 'active') data-confirm="Khách hàng sẽ bị đăng xuất và không thể đăng nhập cho tới khi được mở khóa." data-confirm-title="Khóa tài khoản {{ $customer->full_name }}?" @endif>
                                        @csrf @method('PATCH')
                                        <button @class(['lock-button', 'danger' => $customer->status === 'active'])>
                                            @if ($customer->status === 'active')
                                                <x-icon name="lock" /> Khóa
                                            @else
                                                <x-icon name="unlock" /> Mở khóa
                                            @endif
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->links() }}
        @endif
    </div>
@endsection
