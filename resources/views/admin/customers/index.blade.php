@extends('layouts.admin')

@section('title', 'Quản lý khách hàng')

@php
    $statusLabels = \App\Models\User::STATUSES;
    $indexUrl = route('admin.customers.index', request()->only('search', 'page'));
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
                                    <div class="table-actions">
                                    <a href="{{ route('admin.customers.index', [...request()->only('search', 'page'), 'edit' => $customer->id]) }}" aria-label="Sửa thông tin {{ $customer->full_name }}" title="Sửa thông tin"><x-icon name="edit-2" /></a>
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
                                    <form method="POST" action="{{ route('admin.customers.destroy', [$customer, ...request()->only('search', 'page')]) }}" class="inline-form"
                                          data-confirm="Xóa họ tên, SĐT, email, địa chỉ, yêu thích, lịch sử chat và voucher chưa dùng. Đơn hàng, đánh giá được giữ ở dạng ẩn danh. Không thể hoàn tác."
                                          data-confirm-title="Xóa tài khoản {{ $customer->full_name }}?">
                                        @csrf @method('DELETE')
                                        <button class="danger" aria-label="Xóa tài khoản {{ $customer->full_name }}" title="Xóa tài khoản (yêu cầu xóa dữ liệu)"><x-icon name="trash-2" /></button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->links() }}
        @endif
    </div>

    @if ($editing)
        <dialog class="accessible-dialog admin-form-dialog" aria-label="Sửa thông tin khách hàng" data-autoshow data-close-url="{{ $indexUrl }}">
            <form class="admin-form-modal simple-form-modal" method="POST" action="{{ route('admin.customers.update', $editing) }}">
                @csrf @method('PUT')
                <input type="hidden" name="search" value="{{ request('search') }}">
                <input type="hidden" name="page" value="{{ request('page') }}">
                <div class="admin-modal-head">
                    <div><span>Chỉnh sửa · {{ $editing->authProviderLabel() }}</span><h2>{{ $editing->full_name }}</h2></div>
                    <a href="{{ $indexUrl }}" class="admin-modal-close" aria-label="Đóng"><x-icon name="x" /></a>
                </div>
                <div class="form-grid">
                    <label class="form-field full">
                        <span>Họ và tên *</span>
                        <input name="full_name" value="{{ old('full_name', $editing->full_name) }}" maxlength="100" required>
                        @error('full_name')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Số điện thoại{{ $editing->isSocialAccount() ? '' : ' *' }}</span>
                        <input name="phone" value="{{ old('phone', $editing->phone) }}" inputmode="tel" @required(! $editing->isSocialAccount())>
                        @error('phone')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Email</span>
                        <input type="email" name="email" value="{{ old('email', $editing->email) }}" maxlength="150">
                        @error('email')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field full">
                        <span>Địa chỉ</span>
                        <input name="address" value="{{ old('address', $editing->address) }}" maxlength="255">
                        @error('address')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Trạng thái</span>
                        <select name="status">
                            @foreach ($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $editing->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<small>{{ $message }}</small>@enderror
                    </label>
                    <label class="form-field">
                        <span>Vai trò</span>
                        <select name="role">
                            <option value="customer" @selected(old('role', $editing->role) === 'customer')>Khách hàng</option>
                            <option value="admin" @selected(old('role', $editing->role) === 'admin')>Quản trị viên</option>
                        </select>
                        @error('role')<small>{{ $message }}</small>@enderror
                    </label>
                </div>
                <p class="admin-page-note"><x-icon name="alert-circle" /> Khóa hoặc ngừng hoạt động sẽ đăng xuất khách ngay. Cấp quyền quản trị sẽ đưa tài khoản ra khỏi danh sách khách hàng.</p>
                <div class="admin-modal-actions">
                    <a class="btn btn-light" href="{{ $indexUrl }}">Hủy</a>
                    <button class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
