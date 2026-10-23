{{-- Form gửi đánh giá: chọn sao, nội dung, tối đa 5 ảnh. Chưa đăng nhập thì chuyển tới trang đăng nhập. --}}
<form class="{{ $class }}" method="POST" action="{{ route('reviews.store') }}" enctype="multipart/form-data" data-review-form>
    @csrf
    <input type="hidden" name="target_type" value="{{ $targetType }}">
    <input type="hidden" name="target_id" value="{{ $targetId }}">
    <input type="hidden" name="rating" value="{{ old('rating', 5) }}" data-rating-input>
    @if ($targetType === 'general')
        <h2>{{ $heading }}</h2><p>Chia sẻ cảm nhận về dịch vụ {{ $store['storeName'] }}.</p>
        <label>Mức độ hài lòng</label>
    @else
        <h3>{{ $heading }}</h3>
        <label>Chọn số sao</label>
    @endif
    <div class="star-picker">
        @for ($star = 1; $star <= 5; $star++)
            <button type="button" @class(['active' => $star <= old('rating', 5)]) data-star="{{ $star }}" aria-label="{{ $star }} sao"><x-icon name="star" /></button>
        @endfor
    </div>
    <textarea name="comment" rows="{{ $rows }}" placeholder="{{ $placeholder }}" minlength="10" required>{{ old('comment') }}</textarea>
    <label class="review-upload">
        <input type="file" name="images[]" accept="image/*" multiple data-review-images>
        <span><x-icon name="image" /> Tải ảnh đánh giá</span>
        <small data-review-image-count>0/5 ảnh</small>
    </label>
    <div class="review-image-preview" data-review-image-preview hidden></div>
    @auth
        <button class="btn btn-primary">Gửi đánh giá</button>
    @else
        <a class="btn btn-primary" href="{{ route('login', ['redirect' => request()->getRequestUri()]) }}">Đăng nhập để gửi đánh giá</a>
    @endauth
</form>
