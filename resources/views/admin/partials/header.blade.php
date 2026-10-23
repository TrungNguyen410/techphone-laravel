@php
    $pages = [
        'admin.dashboard' => 'Tổng quan hệ thống',
        'admin.products.index' => 'Quản lý sản phẩm',
        'admin.accessories.index' => 'Quản lý phụ kiện',
        'admin.orders.index' => 'Quản lý đơn hàng',
        'admin.customers.index' => 'Quản lý khách hàng',
        'admin.contacts.index' => 'Quản lý liên hệ hỗ trợ',
        'admin.reviews.index' => 'Quản lý đánh giá',
        'admin.banners.index' => 'Quản lý banner',
        'admin.vouchers.index' => 'Quản lý voucher',
        'admin.categories.index' => 'Quản lý danh mục',
        'admin.brands.index' => 'Quản lý thương hiệu',
        'admin.settings.edit' => 'Cài đặt hệ thống',
    ];
    $title = collect($pages)->first(fn ($label, $route) => request()->routeIs($route)) ?? 'Quản trị';
    $notifications = array_filter([
        $newContacts ? [$newContacts.' yêu cầu liên hệ mới cần xử lý', route('admin.contacts.index', ['status' => 'new'])] : null,
        ['Kiểm tra các đơn hàng đang chờ xác nhận', route('admin.orders.index', ['status' => 'pending'])],
        ['Duyệt các đánh giá mới từ khách hàng', route('admin.reviews.index', ['status' => 'pending'])],
        ['Theo dõi sản phẩm có tồn kho thấp', route('admin.products.index')],
    ]);
    $admin = auth()->user();
@endphp
<header class="admin-header">
    <div>
        <button class="admin-menu-button" type="button" data-admin-sidebar-open aria-label="Mở menu"><x-icon name="menu" /></button>
        <div><small>{{ $store['storeName'] }} Admin</small><h1>{{ $title }}</h1></div>
    </div>
    <div class="admin-header-actions">
        <button class="admin-search-button" type="button" aria-label="Tìm trang quản trị" aria-expanded="false" data-popover-toggle="admin-search-popover"><x-icon name="search" /></button>
        <button class="notification-button" type="button" aria-label="Xem thông báo{{ $newContacts ? ', '.$newContacts.' liên hệ mới' : '' }}" aria-expanded="false" data-popover-toggle="notification-popover">
            <x-icon name="bell" />@if ($newContacts)<span>{{ $newContacts }}</span>@endif
        </button>
        <div class="admin-profile"><div>{{ mb_substr($admin->full_name, 0, 1) }}</div><span><strong>{{ $admin->full_name }}</strong><small>Quản trị viên</small></span></div>
        <form method="POST" action="{{ route('logout') }}" class="inline-form">
            @csrf
            <button class="admin-search-button" aria-label="Đăng xuất" title="Đăng xuất"><x-icon name="log-out" /></button>
        </form>

        <div class="admin-header-popover admin-search-popover" id="admin-search-popover" hidden>
            <div class="admin-popover-input">
                <x-icon name="search" />
                <input placeholder="Tìm trang quản trị..." data-admin-page-search>
                <button type="button" aria-label="Đóng" data-popover-close><x-icon name="x" /></button>
            </div>
            <div class="admin-popover-list">
                @foreach ($pages as $route => $label)
                    <a href="{{ route($route) }}" data-admin-page="{{ \Illuminate\Support\Str::ascii(mb_strtolower($label)) }}">{{ $label }}</a>
                @endforeach
                <p data-admin-page-empty hidden>Không tìm thấy trang phù hợp.</p>
            </div>
        </div>
        <div class="admin-header-popover notification-popover" id="notification-popover" hidden>
            <div class="admin-popover-title"><strong>Thông báo</strong><button type="button" aria-label="Đóng" data-popover-close><x-icon name="x" /></button></div>
            @foreach ($notifications as [$label, $url])
                <a href="{{ $url }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</header>
