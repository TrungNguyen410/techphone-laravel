@extends('layouts.store')

@section('title', $accessory->name)
@section('description', \Illuminate\Support\Str::limit((string) $accessory->description, 155))

@section('content')
    @php
        $isOutOfStock = ! $accessory->isAvailable();
        $gallery = $accessory->galleryImages();
    @endphp
    <main class="page-shell">
        <div class="container">
            <nav class="breadcrumbs">
                <a href="{{ route('home') }}">Trang chủ</a><x-icon name="chevron-right" />
                <a href="{{ route('accessories.index') }}">Phụ kiện</a><x-icon name="chevron-right" />
                <span>{{ $accessory->name }}</span>
            </nav>
            <section class="detail-grid">
                <div class="gallery" data-gallery>
                    <div class="main-image"><img src="{{ media_url($gallery[0] ?? null) }}" alt="{{ $accessory->name }}" data-gallery-main></div>
                    @if (count($gallery) > 1)
                        <div class="thumbnail-row">
                            @foreach ($gallery as $image)
                                <button type="button" @class(['active' => $loop->first]) data-gallery-thumb="{{ media_url($image) }}">
                                    <img src="{{ media_url($image) }}" alt="{{ $accessory->name }} {{ $loop->iteration }}">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="detail-info">
                    <span class="detail-brand">{{ $accessory->brandName() }} · {{ $accessory->categoryName() }}</span>
                    <h1>{{ $accessory->name }}</h1>
                    <div class="detail-rating"><span><x-icon name="star" /> {{ $accessory->rating }}</span><span>Đã bán {{ $accessory->sold }}</span></div>
                    <div class="detail-price">
                        <strong>{{ money($accessory->price) }}</strong>
                        @if ($accessory->old_price > $accessory->price)
                            <del>{{ money($accessory->old_price) }}</del><span>-{{ $accessory->discount_percent }}%</span>
                        @endif
                    </div>
                    <p class="detail-description">{{ $accessory->description }}</p>
                    <div class="promotion-box">
                        <h3>Quyền lợi mua hàng</h3>
                        <p><x-icon name="check" /> Sản phẩm chính hãng, đầy đủ phụ kiện</p>
                        <p><x-icon name="check" /> Đổi mới trong 30 ngày nếu lỗi kỹ thuật</p>
                        <p><x-icon name="check" /> Bảo hành 12 tháng tại {{ $store['storeName'] }}</p>
                    </div>
                    <form method="POST" action="{{ route('cart.add', ['accessory', $accessory->id]) }}" data-purchase-form>
                        @csrf
                        <div class="purchase-row">
                            @include('partials.quantity-stepper', ['max' => $accessory->stock, 'disabled' => $isOutOfStock])
                            <span @class(['in-stock', 'out-of-stock' => $isOutOfStock])>
                                <x-icon name="check" /> {{ $isOutOfStock ? 'Hết hàng' : 'Còn '.$accessory->stock.' sản phẩm' }}
                            </span>
                        </div>
                        <div class="detail-actions">
                            <button class="btn btn-outline-primary" name="redirect" value="" @disabled($isOutOfStock)><x-icon name="shopping-bag" /> Thêm vào giỏ</button>
                            <button class="btn btn-primary" name="redirect" value="cart" @disabled($isOutOfStock)>{{ $isOutOfStock ? 'Hết hàng' : 'Xem giỏ hàng' }}</button>
                        </div>
                    </form>
                </div>
            </section>
            <section class="detail-content-grid single">
                <article class="panel description-panel"><h2>Thông tin phụ kiện</h2><p>{{ $accessory->description }}</p></article>
                <aside class="panel specs-panel">
                    <h2>Thông số</h2>
                    @foreach ($accessory->specifications ?? [] as $key => $value)
                        <div><span>{{ $key }}</span><strong>{{ $value }}</strong></div>
                    @endforeach
                </aside>
            </section>

            @include('partials.review-section', ['target' => $accessory, 'reviews' => $reviews])

            <section class="product-section related-section">
                <div class="section-heading"><div><span>Gợi ý thêm</span><h2>Phụ kiện khác</h2></div></div>
                @include('partials.product-grid', ['items' => $related])
            </section>
        </div>
    </main>
@endsection
