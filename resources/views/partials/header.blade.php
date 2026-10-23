@php
    $user = auth()->user();
    $navItems = [
        ['route' => 'home', 'label' => 'Trang chủ', 'active' => 'home'],
        ['route' => 'products.index', 'label' => 'Điện thoại', 'active' => 'products.*'],
        ['route' => 'accessories.index', 'label' => 'Phụ kiện', 'active' => 'accessories.*'],
        ['route' => 'compare.index', 'label' => 'So sánh', 'active' => 'compare.*'],
        ['route' => 'reviews.index', 'label' => 'Đánh giá', 'active' => 'reviews.*'],
        ['route' => 'contact.index', 'label' => 'Liên hệ', 'active' => 'contact.*'],
    ];
@endphp
<div class="topbar">
    <div class="container topbar-inner">
        <span>Miễn phí vận chuyển đơn từ 10 triệu</span>
        <div><x-icon name="phone-call" /> Hotline: <strong>{{ $store['hotline'] }}</strong></div>
    </div>
</div>
<header class="site-header">
    <div class="container header-main">
        <button class="mobile-menu-button" type="button" data-open-dialog="mobile-navigation-drawer" aria-label="Mở menu" aria-controls="mobile-navigation-drawer">
            <x-icon name="menu" />
        </button>
        @include('partials.brand')
        <form class="header-search" action="{{ route('products.index') }}" method="GET" role="search" data-search-suggest="{{ route('search.suggest') }}">
            <x-icon name="search" />
            <input name="q" value="{{ request()->routeIs('products.index') ? request('q') : '' }}" role="combobox" autocomplete="off"
                   aria-label="Tìm kiếm sản phẩm" aria-autocomplete="list" aria-controls="header-search-suggestions" aria-expanded="false"
                   placeholder="Bạn cần tìm điện thoại nào?">
            <button>Tìm kiếm</button>
            <div class="search-suggestions" id="header-search-suggestions" role="listbox" hidden></div>
        </form>
        <div class="header-actions">
            <a class="header-action favorite-action" href="{{ route('favorites.index') }}">
                <x-icon name="heart" />
                <span><small>Đã lưu</small><strong>Yêu thích</strong></span>
                <b data-wishlist-count @if ($wishlistCount === 0) hidden @endif>{{ $wishlistCount }}</b>
            </a>
            <a class="header-action" href="{{ $user ? route('account.index') : route('login') }}">
                <x-icon name="user" />
                <span>
                    <small>{{ $user ? 'Xin chào' : 'Tài khoản' }}</small>
                    <strong>{{ $user ? $user->shortName() : 'Đăng nhập' }}</strong>
                </span>
                <x-icon name="chevron-down" class="chevron" />
            </a>
            <a class="header-action cart-action" href="{{ route('cart.index') }}">
                <x-icon name="shopping-bag" />
                <span><small>Giỏ hàng</small><strong data-cart-count-text>{{ $cartCount }} sản phẩm</strong></span>
                <b data-cart-count @if ($cartCount === 0) hidden @endif>{{ $cartCount }}</b>
            </a>
        </div>
    </div>
    <nav class="desktop-nav">
        <div class="container nav-inner">
            @foreach ($navItems as $item)
                <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs($item['active'])])>{{ $item['label'] }}</a>
            @endforeach
            @if ($user?->isAdmin())
                <a href="{{ route('admin.dashboard') }}">Quản trị</a>
            @endif
            @auth
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button class="nav-logout">Đăng xuất</button>
                </form>
            @endauth
        </div>
    </nav>
</header>

<dialog class="accessible-dialog mobile-drawer open" id="mobile-navigation-drawer" aria-label="Menu điều hướng">
    <div class="mobile-drawer-header">
        @include('partials.brand')
        <button type="button" data-close-dialog aria-label="Đóng menu"><x-icon name="x" /></button>
    </div>
    <form class="mobile-search" action="{{ route('products.index') }}" method="GET">
        <input name="q" placeholder="Tìm sản phẩm...">
        <button><x-icon name="search" /></button>
    </form>
    <nav aria-label="Điều hướng di động">
        <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])>Trang chủ</a>
        <a href="{{ route('products.index') }}" @class(['active' => request()->routeIs('products.*')])>Điện thoại</a>
        <a href="{{ route('accessories.index') }}" @class(['active' => request()->routeIs('accessories.*')])>Phụ kiện</a>
        <a href="{{ route('compare.index') }}" @class(['active' => request()->routeIs('compare.*')])>So sánh sản phẩm</a>
        <a href="{{ route('reviews.index') }}" @class(['active' => request()->routeIs('reviews.*')])>Đánh giá</a>
        <a href="{{ route('favorites.index') }}" @class(['active' => request()->routeIs('favorites.*')])>Sản phẩm yêu thích ({{ $wishlistCount }})</a>
        <a href="{{ route('contact.index') }}" @class(['active' => request()->routeIs('contact.*')])>Liên hệ</a>
        <a href="{{ $user ? route('account.index') : route('login') }}">{{ $user ? 'Tài khoản của tôi' : 'Đăng nhập' }}</a>
        @if ($user?->isAdmin())
            <a href="{{ route('admin.dashboard') }}">Quản trị</a>
        @endif
    </nav>
</dialog>
