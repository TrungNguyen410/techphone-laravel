{{-- Khu vực đánh giá trên trang chi tiết. $target: Product hoặc Accessory; $reviews: đánh giá đã duyệt --}}
@php
    $isAccessory = $target->itemType() === 'accessory';
    $total = $reviews->count();
    $average = $total ? $reviews->avg('rating') : 0;
@endphp
<section class="product-reviews" data-review-section>
    <div class="section-heading"><div><span>Phản hồi thực tế</span><h2>{{ $isAccessory ? 'Đánh giá phụ kiện' : 'Đánh giá sản phẩm' }}</h2></div></div>
    <div class="product-review-summary panel" aria-label="Tổng quan đánh giá">
        <div class="review-average">
            <strong>{{ number_format($average, 1) }}</strong>
            <span><x-icon name="star" /> trên 5</span>
            <small>{{ $total }} đánh giá đã duyệt</small>
        </div>
        <div class="review-distribution">
            @foreach ([5, 4, 3, 2, 1] as $star)
                @php $count = $reviews->where('rating', $star)->count(); @endphp
                <button type="button" aria-pressed="false" data-review-filter="{{ $star }}">
                    <span>{{ $star }} <x-icon name="star" /></span>
                    <i><b style="transform: scaleX({{ $total ? $count / $total : 0 }})"></b></i>
                    <small>{{ $count }}</small>
                </button>
            @endforeach
        </div>
        <button type="button" class="review-filter-reset" disabled data-review-filter-reset>Đang xem tất cả</button>
    </div>
    <div class="review-layout">
        @include('partials.review-form', [
            'class' => 'review-form panel',
            'heading' => 'Chia sẻ trải nghiệm',
            'targetType' => $target->itemType(),
            'targetId' => $target->id,
            'placeholder' => $isAccessory ? 'Phụ kiện này có điểm gì nổi bật?' : 'Sản phẩm này có điểm gì nổi bật?',
            'rows' => 4,
        ])
        <div class="review-list">
            @forelse ($reviews as $review)
                <article class="review-item" data-rating="{{ $review->rating }}">
                    @include('partials.review-body', ['review' => $review])
                </article>
            @empty
                @include('partials.empty-state', ['title' => 'Chưa có đánh giá', 'description' => 'Hãy là người đầu tiên chia sẻ trải nghiệm.'])
            @endforelse
        </div>
    </div>
</section>
