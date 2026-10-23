@extends('layouts.store')

@section('title', $policy['title'])

@section('content')
    <main class="page-shell policy-page">
        <div class="container policy-layout">
            <aside class="policy-index">
                <span class="eyebrow">Trung tâm chính sách</span>
                @foreach ($policies as $key => $item)
                    <a href="{{ route('policies.show', $key) }}" @class(['active' => $key === $type])>{{ $item['title'] }}</a>
                @endforeach
            </aside>
            <article class="policy-content">
                <span class="eyebrow">{{ $policy['eyebrow'] }}</span>
                <h1>{{ $policy['title'] }}</h1>
                <p class="policy-intro">{{ $policy['intro'] }}</p>
                @foreach ($policy['sections'] as [$title, $content])
                    <section><h2>{{ $title }}</h2><p>{{ $content }}</p></section>
                @endforeach
                <div class="policy-notice">
                    <x-icon name="alert-circle" />
                    <p>
                        Đây là nội dung vận hành hiện có của {{ $store['storeName'] }}. Điều khoản chi tiết theo từng hãng,
                        sản phẩm và hợp đồng nhà cung cấp cần được chủ shop xác nhận trước khi dùng như cam kết pháp lý.
                    </p>
                </div>
                <a class="btn btn-light" href="{{ route('contact.index') }}"><x-icon name="arrow-left" /> Liên hệ hỗ trợ</a>
            </article>
        </div>
    </main>
@endsection
