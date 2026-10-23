<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="@yield('description', 'TechPhone - Điện thoại và phụ kiện chính hãng, giá tốt, giao nhanh.')">
@php $pageTitle = trim($__env->yieldContent('title')); @endphp
<title>{{ $pageTitle !== '' ? $pageTitle.' | '.($store['storeName'] ?? 'TechPhone') : ($store['storeName'] ?? 'TechPhone').' | Điện thoại & Phụ kiện' }}</title>
<link rel="icon" href="{{ asset('favicon.ico') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@500&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
@foreach (['tokens', 'main', 'admin', 'responsive', 'redesign', 'app'] as $sheet)
    <link rel="stylesheet" href="{{ asset('css/'.$sheet.'.css') }}?v={{ filemtime(public_path('css/'.$sheet.'.css')) }}">
@endforeach
@stack('head')
