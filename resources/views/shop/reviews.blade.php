@extends('layouts.store')

@section('title', 'Đánh giá khách hàng')

@section('content')
    <main class="page-shell reviews-page">
        <div class="container">
            <div class="page-hero">
                <div><span>Khách hàng nói gì</span><h1>Trải nghiệm thật, chia sẻ thật</h1><p>Những phản hồi giúp {{ $store['storeName'] }} phục vụ bạn tốt hơn mỗi ngày.</p></div>
                <x-icon name="message-square" />
            </div>
            <section class="review-summary panel">
                <div class="average-score">
                    <strong>{{ number_format($average, 1) }}</strong>
                    <div>
                        <span>@for ($i = 0; $i < 5; $i++)<x-icon name="star" :class="$i < round($average) ? 'filled' : ''" />@endfor</span>
                        <small>{{ $total }} đánh giá đã duyệt</small>
                    </div>
                </div>
                <div class="rating-filters">
                    <a href="{{ route('reviews.index') }}" @class(['rating-filter', 'active' => $filter === 0])>Tất cả</a>
                    @foreach ([5, 4, 3, 2, 1] as $star)
                        <a href="{{ route('reviews.index', ['rating' => $star]) }}" @class(['rating-filter', 'active' => $filter === $star])>{{ $star }} <x-icon name="star" /></a>
                    @endforeach
                </div>
            </section>
            <div class="reviews-layout">
                <section class="public-review-list">
                    @forelse ($reviews as $review)
                        <article class="review-item panel">
                            @include('partials.review-body', ['review' => $review])
                        </article>
                    @empty
                        @include('partials.empty-state', ['title' => 'Chưa có đánh giá', 'description' => 'Hãy là người đầu tiên chia sẻ trải nghiệm.'])
                    @endforelse
                </section>
                @include('partials.review-form', [
                    'class' => 'panel global-review-form',
                    'heading' => 'Gửi đánh giá',
                    'targetType' => 'general',
                    'targetId' => null,
                    'placeholder' => 'Hãy kể cho chúng tôi về trải nghiệm của bạn...',
                    'rows' => 6,
                ])
            </div>
        </div>
    </main>
@endsection
