<div class="review-avatar">{{ mb_substr($review->user_name, 0, 1) }}</div>
<div>
    <div class="review-author">
        <strong>{{ $review->user_name }}</strong>
        <span>{{ format_date($review->created_at) }}</span>
        @if ($review->verified_purchase)
            <small class="verified-review">Đã mua hàng</small>
        @endif
    </div>
    <div class="review-stars">
        @for ($i = 0; $i < 5; $i++)
            <x-icon name="star" :class="$i < $review->rating ? 'filled' : ''" />
        @endfor
    </div>
    <p>{{ $review->comment }}</p>
    @if (! empty($review->images))
        <div class="review-images">
            @foreach ($review->images as $image)
                <img src="{{ media_url($image) }}" alt="Ảnh đánh giá">
            @endforeach
        </div>
    @endif
</div>
