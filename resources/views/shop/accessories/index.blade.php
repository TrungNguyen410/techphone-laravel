@extends('layouts.store')

@section('title', 'Phụ kiện chính hãng')

@section('content')
    <main class="page-shell">
        <div class="container">
            <div class="page-hero small">
                <div><span>Hoàn thiện trải nghiệm</span><h1>Phụ kiện chính hãng</h1></div>
                <p>Tai nghe, sạc nhanh, pin dự phòng và đồng hồ thông minh được chọn lọc cho thiết bị của bạn.</p>
            </div>

            @include('partials.catalog-toolbar', [
                'action' => route('accessories.index'),
                'placeholder' => 'Tìm phụ kiện theo tên, hãng hoặc loại...',
            ])

            <div class="catalog-layout">
                <div class="filter-mobile-wrap" data-filter-panel>
                    <button class="filter-mobile-close" type="button" data-filter-close><x-icon name="x" /></button>
                    <form class="product-filter" method="GET" action="{{ route('accessories.index') }}" data-auto-submit-form>
                        @foreach (collect($filters)->only(['q', 'sort'])->filter() as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        <div class="filter-heading">
                            <h3>Bộ lọc phụ kiện</h3>
                            <a class="filter-reset" href="{{ route('accessories.index') }}"><x-icon name="rotate-ccw" /> Đặt lại</a>
                        </div>
                        <div class="filter-group">
                            <label for="filter-brand">Thương hiệu</label>
                            <select id="filter-brand" name="brand" aria-label="Thương hiệu phụ kiện">
                                <option value="">Tất cả thương hiệu</option>
                                @foreach ($options['brands'] as $brand)
                                    <option @selected(($filters['brand'] ?? '') === $brand)>{{ $brand }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-category">Loại phụ kiện</label>
                            <select id="filter-category" name="category" aria-label="Loại phụ kiện">
                                <option value="">Tất cả loại phụ kiện</option>
                                @foreach ($options['categories'] as $category)
                                    <option @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filter-price">Khoảng giá</label>
                            <select id="filter-price" name="price" aria-label="Khoảng giá phụ kiện">
                                <option value="">Tất cả mức giá</option>
                                @foreach (App\Services\CatalogService::ACCESSORY_PRICE_RANGES as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['price'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <noscript><button class="btn btn-primary w-100">Áp dụng</button></noscript>
                    </form>
                </div>
                <div class="drawer-overlay" data-filter-close hidden></div>
                <div class="catalog-results">
                    <div class="result-count"><x-icon name="search" /> Tìm thấy <strong>{{ $accessories->total() }}</strong> phụ kiện</div>
                    @include('partials.product-grid', ['items' => $accessories])
                    {{ $accessories->links() }}
                </div>
            </div>

            <section class="benefit-grid">
                <div><x-icon name="headphones" /><span><strong>Phụ kiện tương thích</strong><small>Chọn đúng chuẩn cho từng thiết bị</small></span></div>
                <div><x-icon name="search" /><span><strong>Dễ lọc, dễ so sánh</strong><small>Theo hãng, loại và khoảng giá</small></span></div>
            </section>
        </div>
    </main>
@endsection
