<!doctype html>
<html lang="vi">
<head>
    @include('partials.head')
</head>
<body>
    <div class="store-layout">
        @include('partials.header')
        <div class="store-content">
            @yield('content')
        </div>
        @include('partials.footer')
        @include('partials.support-launcher')
    </div>
    @include('partials.toasts')
    @include('partials.confirm-dialog')
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    @stack('scripts')
</body>
</html>
