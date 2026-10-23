@php
    $type = $item->itemType();
    $isOutOfStock = ! $item->isAvailable();
    $favorite = app(App\Services\ShopperPreferences::class)->inWishlist($item);
    $compared = $type === 'product' && in_array($item->id, app(App\Services\ShopperPreferences::class)->compared(), true);
@endphp
<article class="product-card">
    <a href="{{ $item->detailUrl() }}" class="product-card-link">
        <div class="product-image-wrap">
            <img src="{{ media_url($item->image) }}" alt="{{ $item->name }}" loading="lazy">
            @if ($item->discount_percent > 0)
                <span class="discount-badge">-{{ $item->discount_percent }}%</span>
            @endif
            @if ($isOutOfStock)
                <span class="stock-badge">Hết hàng</span>
            @endif
        </div>
        <div class="product-card-body">
            <span class="product-brand">{{ $item->brandName() }}</span>
            <h3>{{ $item->name }}</h3>
            @if ($type === 'product')
                <div class="spec-chips"><span>{{ $item->ram }}</span><span>{{ $item->storage }}</span></div>
            @endif
            <div class="price-row">
                <strong>{{ money($item->price) }}</strong>
                @if ($item->old_price > $item->price)
                    <del>{{ money($item->old_price) }}</del>
                @endif
            </div>
            <div class="product-meta">
                <span><x-icon name="star" /> {{ $item->rating }}</span>
                <span>Đã bán {{ $item->sold }}</span>
            </div>
        </div>
    </a>
    <form class="inline-form" method="POST" action="{{ route('wishlist.toggle', [$type, $item->id]) }}" data-ajax="wishlist">
        @csrf
        <button type="submit" @class(['wishlist-button', 'active' => $favorite]) aria-pressed="{{ $favorite ? 'true' : 'false' }}"
                aria-label="{{ $favorite ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích' }}">
            <x-icon name="heart" />
        </button>
    </form>
    @if ($type === 'product')
        <form class="inline-form" method="POST" action="{{ route('compare.toggle', $item) }}" data-ajax="compare">
            @csrf
            <button type="submit" @class(['compare-card-button', 'active' => $compared]) aria-pressed="{{ $compared ? 'true' : 'false' }}"
                    aria-label="{{ $compared ? 'Bỏ khỏi so sánh' : 'Thêm vào so sánh' }}">
                <x-icon name="bar-chart-2" />
            </button>
        </form>
    @endif
    <form class="product-card-actions" method="POST" action="{{ route('cart.add', [$type, $item->id]) }}" data-ajax="cart">
        @csrf
        <button class="add-cart-button" @disabled($isOutOfStock)>
            <x-icon name="shopping-cart" /> {{ $isOutOfStock ? 'Hết hàng' : 'Thêm vào giỏ' }}
        </button>
    </form>
</article>
