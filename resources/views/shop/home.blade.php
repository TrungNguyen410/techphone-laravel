@extends('layouts.store')

@section('content')
    <section class="hero-section">
        <div class="container">
            <div class="hero-slider" data-hero-slider>
                @foreach ($banners as $banner)
                    <a href="{{ $banner->link }}" @class(['hero-slide', 'active' => $loop->first])
                       @unless ($loop->first) aria-hidden="true" tabindex="-1" style="visibility:hidden;pointer-events:none" @endunless data-slide>
                        <img src="{{ media_url($banner->image) }}" alt="{{ $banner->title }}">
                        <span class="hero-mobile-copy" aria-hidden="true">
                            <strong>{{ $banner->title }}</strong>
                            @if ($banner->description)<small>{{ $banner->description }}</small>@endif
                            <b>Khám phá ngay</b>
                        </span>
                    </a>
                @endforeach
                <div class="slider-dots">
                    @foreach ($banners as $banner)
                        <button type="button" @class(['active' => $loop->first]) aria-label="Xem banner {{ $loop->iteration }}"
                                aria-pressed="{{ $loop->first ? 'true' : 'false' }}" data-slide-dot="{{ $loop->index }}"></button>
                    @endforeach
                </div>
                @if ($banners->count() > 1)
                    <button type="button" class="slider-pause-button" aria-label="Tạm dừng banner" data-slider-pause>
                        <span data-pause-icon><x-icon name="pause" /></span>
                        <span data-play-icon hidden><x-icon name="play" /></span>
                    </button>
                @endif
            </div>
        </div>
    </section>

    <section class="container category-section">
        <div class="section-heading compact">
            <div><span>Mua sắm dễ dàng</span><h2>Danh mục nổi bật</h2></div>
        </div>
        <div class="category-marquee" role="region" aria-roledescription="carousel" aria-label="Danh mục nổi bật" data-category-marquee>
            <div class="category-marquee-track is-looping">
                @foreach ([false, true] as $clone)
                    <div class="category-marquee-set" @if ($clone) aria-hidden="true" @endif>
                        @foreach ($categories as $category)
                            <a href="{{ $category['url'] }}" class="category-card" @if ($clone) tabindex="-1" @endif>
                                <div class="category-icon {{ $category['color'] }}"><x-icon :name="$category['icon']" /></div>
                                <strong>{{ $category['name'] }}</strong>
                                <span>Khám phá <x-icon name="arrow-right" /></span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <button type="button" class="carousel-pause-button" aria-label="Tạm dừng danh mục" data-marquee-pause>
                <span data-pause-icon><x-icon name="pause" /></span>
                <span data-play-icon hidden><x-icon name="play" /></span>
            </button>
        </div>
    </section>

    <section class="container product-section">
        <div class="section-heading">
            <div><span>Xếp hạng theo số lượng đã bán</span><h2>Sản phẩm bán chạy</h2></div>
            <a href="{{ route('products.index') }}">Xem tất cả <x-icon name="arrow-right" /></a>
        </div>
        @include('partials.product-grid', ['items' => $hotProducts])
    </section>

    @if ($personalized->isNotEmpty())
        <section class="container product-section personalized-section">
            <div class="section-heading">
                <div><span>Dựa trên sản phẩm bạn đã xem</span><h2>Gợi ý phù hợp để xem tiếp</h2></div>
            </div>
            @include('partials.product-grid', ['items' => $personalized])
        </section>
    @endif

    <section class="sale-band">
        <div class="container">
            <div class="section-heading light">
                <div><span>Giảm từ 10%, ưu tiên số tiền tiết kiệm cao</span><h2>Ưu đãi tiết kiệm nhất</h2></div>
                <a href="{{ route('products.index') }}">Săn ưu đãi <x-icon name="arrow-right" /></a>
            </div>
            @include('partials.product-grid', ['items' => $saleProducts])
        </div>
    </section>

    <section class="container product-section" id="accessories">
        <div class="section-heading">
            <div><span>Điểm từ 4.5 sao và có ít nhất 20 lượt bán</span><h2>Phụ kiện nổi bật</h2></div>
            <a href="{{ route('accessories.index') }}">Xem tất cả <x-icon name="arrow-right" /></a>
        </div>
        @include('partials.product-grid', ['items' => $featuredAccessories])
    </section>

    <section class="store-story">
        <div class="container store-story-grid">
            <div>
                <span class="eyebrow">An tâm chọn {{ $store['storeName'] }}</span>
                <h2>Công nghệ tốt hơn, trải nghiệm gần gũi hơn</h2>
                <p>
                    Mỗi thiết bị đều được kiểm định kỹ, công khai tình trạng và đi kèm chính sách bảo hành
                    minh bạch. Đội ngũ của chúng tôi luôn sẵn sàng giúp bạn chọn đúng sản phẩm.
                </p>
                <a class="btn btn-light btn-lg" href="{{ route('contact.index') }}">Tìm hiểu về chúng tôi</a>
            </div>
            <div class="story-stats">
                <div><strong>Kiểm định</strong><span>Tình trạng sản phẩm</span></div>
                <div><strong>Minh bạch</strong><span>Giá và phí giao hàng</span></div>
                <div><strong>Toàn quốc</strong><span>Phạm vi giao nhận</span></div>
                <div><strong>Hậu mãi</strong><span>Tra cứu và bảo hành</span></div>
            </div>
        </div>
    </section>

    <section class="container benefit-grid">
        <div><x-icon name="shield" /><span><strong>Sản phẩm kiểm định</strong><small>Chất lượng được cam kết</small></span></div>
        <div><x-icon name="truck" /><span><strong>Giao hàng toàn quốc</strong><small>Nhanh chóng, an toàn</small></span></div>
        <div><x-icon name="refresh-ccw" /><span><strong>Đổi trả linh hoạt</strong><small>Trong vòng 30 ngày</small></span></div>
        <div><x-icon name="award" /><span><strong>Bảo hành tận tâm</strong><small>Hỗ trợ rõ ràng</small></span></div>
    </section>
@endsection
