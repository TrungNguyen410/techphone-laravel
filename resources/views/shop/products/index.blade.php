@extends('layouts.store')

@section('title', 'Điện thoại chính hãng')

@section('content')
    <main class="page-shell">
        <div class="container">
            <div class="page-hero small">
                <div><span>Khám phá thiết bị phù hợp</span><h1>Điện thoại chính hãng</h1></div>
                <p>So sánh dễ dàng, ưu đãi minh bạch và bảo hành tận tâm.</p>
            </div>

            @include('partials.catalog-toolbar', [
                'action' => route('products.index'),
                'placeholder' => 'Tìm theo tên hoặc thương hiệu...',
            ])

            <div class="catalog-layout">
                <div class="filter-mobile-wrap" data-filter-panel>
                    <button class="filter-mobile-close" type="button" data-filter-close><x-icon name="x" /></button>
                    <form class="product-filter" method="GET" action="{{ route('products.index') }}" data-auto-submit-form>
                        @foreach (collect($filters)->only(['q', 'sort'])->filter() as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <div class="filter-heading">
                            <h3>Bộ lọc sản phẩm</h3>
                            <a class="filter-reset" href="{{ route('products.index') }}"><x-icon name="rotate-ccw" /> Đặt lại</a>
                        </div>
                        <div class="filter-group">
                            <label for="filter-brand">Thương hiệu</label>
                            <select id="filter-brand" name="brand">
                                <option value="">Tất cả thương hiệu</option>
                                @foreach ($options['brands'] as $brand)
                                    <option @selected(($filters['brand'] ?? '') === $brand)>{{ $brand }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-price">Khoảng giá</label>
                            <select id="filter-price" name="price">
                                <option value="">Tất cả mức giá</option>
                                @foreach (App\Services\CatalogService::PRODUCT_PRICE_RANGES as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['price'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-ram">RAM</label>
                            <select id="filter-ram" name="ram">
                                <option value="">Tất cả dung lượng</option>
                                @foreach ($options['ram'] as $ram)
                                    <option @selected(($filters['ram'] ?? '') === $ram)>{{ $ram }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-storage">Bộ nhớ</label>
                            <select id="filter-storage" name="storage">
                                <option value="">Tất cả bộ nhớ</option>
                                @foreach ($options['storage'] as $storage)
                                    <option @selected(($filters['storage'] ?? '') === $storage)>{{ $storage }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-battery">Dung lượng pin</label>
                            <select id="filter-battery" name="battery">
                                <option value="">Tất cả</option>
                                <option value="5000" @selected(($filters['battery'] ?? '') === '5000')>Từ 5.000 mAh</option>
                                <option value="4700" @selected(($filters['battery'] ?? '') === '4700')>Từ 4.700 mAh</option>
                            </select>
                        </div>
                        <noscript><button class="btn btn-primary w-100">Áp dụng</button></noscript>
                    </form>
                </div>
                <div class="drawer-overlay" data-filter-close hidden></div>
                <div class="catalog-results">
                    <div class="result-count"><x-icon name="search" /> Tìm thấy <strong>{{ $products->total() }}</strong> sản phẩm</div>
                    @include('partials.product-grid', ['items' => $products])
                    {{ $products->links() }}
                </div>
            </div>
        </div>
    </main>
@endsection
