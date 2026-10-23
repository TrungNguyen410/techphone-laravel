@php
    $links = [
        ['admin.dashboard', 'Tổng quan', 'grid', 'admin.dashboard'],
        ['admin.products.index', 'Sản phẩm', 'smartphone', 'admin.products.*'],
        ['admin.accessories.index', 'Phụ kiện', 'shopping-bag', 'admin.accessories.*'],
        ['admin.orders.index', 'Đơn hàng', 'package', 'admin.orders.*'],
        ['admin.customers.index', 'Khách hàng', 'users', 'admin.customers.*'],
        ['admin.contacts.index', 'Liên hệ hỗ trợ', 'message-square', 'admin.contacts.*'],
        ['admin.reviews.index', 'Đánh giá', 'message-square', 'admin.reviews.*'],
        ['admin.banners.index', 'Banner', 'image', 'admin.banners.*'],
        ['admin.vouchers.index', 'Voucher', 'tag', 'admin.vouchers.*'],
        ['admin.categories.index', 'Danh mục', 'layers', 'admin.categories.*'],
        ['admin.brands.index', 'Thương hiệu', 'box', 'admin.brands.*'],
        ['admin.settings.edit', 'Cài đặt', 'settings', 'admin.settings.*'],
    ];
@endphp
<aside class="admin-sidebar" data-admin-sidebar>
    <div class="admin-brand">
        <span class="brand-mark">{{ mb_substr($store['storeName'], 0, 1) }}</span>
        <span>{{ $store['storeName'] }}<small>ADMIN CENTER</small></span>
        <button type="button" data-admin-sidebar-close aria-label="Đóng menu"><x-icon name="x" /></button>
    </div>
    <nav>
        @foreach ($links as [$route, $label, $icon, $pattern])
            <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])><x-icon :name="$icon" /><span>{{ $label }}</span></a>
        @endforeach
    </nav>
    <div class="admin-sidebar-bottom">
        <a href="{{ route('home') }}"><x-icon name="home" /><span>Về cửa hàng</span></a>
    </div>
</aside>
