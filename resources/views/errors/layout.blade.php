<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code') · {{ $store['storeName'] ?? 'TechPhone' }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600&family=Space+Grotesk:wght@600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    @foreach (['tokens', 'main', 'redesign', 'app'] as $sheet)
        <link rel="stylesheet" href="{{ asset('css/'.$sheet.'.css') }}">
    @endforeach
</head>
<body>
    <main class="not-found-page">
        <div>
            <strong>@yield('code')</strong>
            <h1>@yield('heading')</h1>
            <p>@yield('message')</p>
            <a class="btn btn-primary" href="{{ url('/') }}">Về trang chủ</a>
        </div>
    </main>
</body>
</html>
