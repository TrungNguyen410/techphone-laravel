@extends('layouts.store')

@section('title', 'Sản phẩm yêu thích')

@section('content')
    @php $total = $products->count() + $accessories->count(); @endphp
    <main class="page-shell favorites-page">
        <div class="container">
            <div class="page-hero small">
                <div><span>Danh sách của bạn</span><h1>Sản phẩm yêu thích</h1></div>
                <p>{{ $total ? $total.' sản phẩm đang được lưu' : 'Lưu sản phẩm để dễ dàng xem lại sau.' }}</p>
            </div>
            @if ($total === 0)
                @include('partials.empty-state', [
                    'icon' => 'heart',
                    'title' => 'Chưa có sản phẩm yêu thích',
                    'description' => 'Nhấn biểu tượng trái tim trên sản phẩm bạn quan tâm.',
                    'actionLabel' => 'Khám phá sản phẩm',
                    'actionUrl' => route('products.index'),
                ])
            @else
                @if ($products->isNotEmpty())
                    <section class="product-section">
                        <div class="section-heading compact"><div><h2>Điện thoại</h2></div></div>
                        @include('partials.product-grid', ['items' => $products])
                    </section>
                @endif
                @if ($accessories->isNotEmpty())
                    <section class="product-section">
                        <div class="section-heading compact"><div><h2>Phụ kiện</h2></div></div>
                        @include('partials.product-grid', ['items' => $accessories])
                    </section>
                @endif
            @endif
        </div>
    </main>
@endsection
