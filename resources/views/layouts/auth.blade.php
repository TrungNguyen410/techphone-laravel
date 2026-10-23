<!doctype html>
<html lang="vi">
<head>
    @include('partials.head')
</head>
<body>
    <main class="auth-page @yield('page-class')">
        <div class="auth-visual">
            <div>@yield('visual')</div>
        </div>
        <div class="auth-panel">
            @include('partials.brand')
            @yield('content')
        </div>
    </main>
    @include('partials.toasts')
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
