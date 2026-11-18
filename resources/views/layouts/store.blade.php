<!doctype html>
<html lang="vi">
<head>
    @include('partials.head')
</head>
<body>
    <a class="skip-link" href="#main-content">Bỏ qua tới nội dung</a>
    <div class="store-layout">
        @include('partials.header')
        <div class="store-content" id="main-content" tabindex="-1">
            @yield('content')
        </div>
        @include('partials.footer')
        @include('partials.support-launcher')
        @include('partials.chat-widget')
    </div>
    @include('partials.toasts')
    @include('partials.confirm-dialog')
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    @stack('scripts')
</body>
</html>
