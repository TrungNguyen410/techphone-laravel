<!doctype html>
<html lang="vi">
<head>
    @include('partials.head')
</head>
<body>
    <div class="admin-layout">
        @include('admin.partials.sidebar')
        <div class="admin-sidebar-overlay" data-admin-sidebar-close hidden></div>
        <div class="admin-main">
            @include('admin.partials.header')
            <main class="admin-content">
                @yield('content')
            </main>
        </div>
    </div>
    @include('partials.toasts')
    @include('partials.confirm-dialog')
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    @stack('scripts')
</body>
</html>
