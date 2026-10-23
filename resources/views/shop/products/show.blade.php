@extends('layouts.store')

@section('title', $product->name)
@section('description', \Illuminate\Support\Str::limit((string) $product->description, 155))

@push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'image' => array_map('media_url', $product->galleryImages()),
        'description' => $product->description,
        'sku' => (string) $product->id,
        'brand' => ['@type' => 'Brand', 'name' => $product->brandName()],
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'VND',
            'price' => $product->price,
            'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => route('products.show', $product),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    @php
        $isOutOfStock = ! $product->isAvailable();
        $gallery = $product->galleryImages();
    @endphp
    <main class="page-shell">
        <div class="container">
            <nav class="breadcrumbs">
                <a href="{{ route('home') }}">Trang chủ</a><x-icon name="chevron-right" />
                <a href="{{ route('products.index') }}">Điện thoại</a><x-icon name="chevron-right" />
                <span>{{ $product->name }}</span>
            </nav>
            <section class="detail-grid">
                <div class="gallery" data-gallery>
                    <div class="main-image"><img src="{{ media_url($gallery[0] ?? null) }}" alt="{{ $product->name }}" data-gallery-main></div>
                    <div class="thumbnail-row">
                        @foreach ($gallery as $image)
                            <button type="button" @class(['active' => $loop->first]) data-gallery-thumb="{{ media_url($image) }}">
                                <img src="{{ media_url($image) }}" alt="{{ $product->name }} {{ $loop->iteration }}">
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="detail-info">
                    <span class="detail-brand">{{ $product->brandName() }}</span>
                    <h1>{{ $product->name }}</h1>
                    <div class="detail-rating"><span><x-icon name="star" /> {{ $product->rating }}</span><span>{{ $product->sold }} sản phẩm đã bán</span></div>
                    <div class="detail-price">
                        <strong>{{ money($product->price) }}</strong>
                        @if ($product->old_price > $product->price)
                            <del>{{ money($product->old_price) }}</del>
                            <span>Tiết kiệm {{ $product->discount_percent }}%</span>
                        @endif
                    </div>
                    <div class="quick-specs">
                        <div><small>RAM</small><strong>{{ $product->ram }}</strong></div>
                        <div><small>Bộ nhớ</small><strong>{{ $product->storage }}</strong></div>
                        <div><small>Màn hình</small><strong>{{ explode(',', $product->screen)[0] }}</strong></div>
                        <div><small>Pin</small><strong>{{ $product->battery }}</strong></div>
                    </div>
                    <div class="promotion-box">
                        <h3><x-icon name="zap" /> Ưu đãi dành riêng cho bạn</h3>
                        <p><x-icon name="check" /> Giảm thêm 200.000đ khi thanh toán chuyển khoản</p>
                        <p><x-icon name="check" /> Tặng gói bảo hành mở rộng 12 tháng</p>
                        <p><x-icon name="check" /> Miễn phí giao hàng toàn quốc</p>
                    </div>
                    <form method="POST" action="{{ route('cart.add', ['product', $product->id]) }}" data-purchase-form>
                        @csrf
                        <div class="purchase-row">
                            @include('partials.quantity-stepper', ['max' => $product->stock, 'disabled' => $isOutOfStock])
                            <span @class(['in-stock', 'out-of-stock' => $isOutOfStock])>
                                <x-icon name="check" /> {{ $isOutOfStock ? 'Hết hàng' : 'Còn '.$product->stock.' sản phẩm' }}
                            </span>
                        </div>
                        <div class="detail-actions">
                            <button class="btn btn-outline-primary" name="redirect" value="" @disabled($isOutOfStock)><x-icon name="shopping-bag" /> Thêm vào giỏ</button>
                            <button class="btn btn-primary" name="redirect" value="checkout" @disabled($isOutOfStock)>{{ $isOutOfStock ? 'Hết hàng' : 'Mua ngay' }}</button>
                            <button class="btn btn-light" type="submit" form="compare-form"><x-icon name="bar-chart-2" /> {{ $isCompared ? 'Bỏ so sánh' : 'So sánh' }}</button>
                        </div>
                    </form>
                    <form id="compare-form" method="POST" action="{{ route('compare.toggle', $product) }}">@csrf</form>
                    <div class="detail-assurances">
                        <span><x-icon name="shield" /> Bảo hành 12 tháng</span>
                        <span><x-icon name="truck" /> Giao nhanh toàn quốc</span>
                    </div>
                </div>
            </section>

            <section class="detail-content-grid">
                <article class="panel description-panel"><h2>Mô tả sản phẩm</h2><p>{{ $product->description }}</p></article>
                <aside class="panel specs-panel">
                    <h2>Thông số kỹ thuật</h2>
                    @foreach ($product->specifications ?? [] as $key => $value)
                        <div><span>{{ $key }}</span><strong>{{ $value }}</strong></div>
                    @endforeach
                </aside>
            </section>

            @include('partials.review-section', ['target' => $product, 'reviews' => $reviews])

            @if ($recentlyViewed->isNotEmpty())
                <section class="product-section recently-viewed-section">
                    <div class="section-heading"><div><span>Xem lại nhanh</span><h2>Sản phẩm bạn đã xem</h2></div></div>
                    @include('partials.product-grid', ['items' => $recentlyViewed])
                </section>
            @endif

            @if ($compatibleAccessories->isNotEmpty())
                <section class="product-section compatible-accessories-section">
                    <div class="section-heading"><div><span>Cùng hãng hoặc dùng chung chuẩn kết nối</span><h2>Phụ kiện mua kèm</h2></div></div>
                    @include('partials.product-grid', ['items' => $compatibleAccessories])
                </section>
            @endif

            @if ($related->isNotEmpty())
                <section class="product-section related-section">
                    <div class="section-heading"><div><span>Có thể bạn quan tâm</span><h2>Sản phẩm liên quan</h2></div></div>
                    @include('partials.product-grid', ['items' => $related])
                </section>
            @endif
        </div>
    </main>
@endsection
