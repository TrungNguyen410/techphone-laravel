@extends('layouts.store')

@section('title', 'So sánh điện thoại')

@section('content')
    <main class="page-shell compare-page">
        @if ($products->isEmpty())
            <div class="container narrow-page">
                @include('partials.empty-state', [
                    'title' => 'Chưa có sản phẩm để so sánh',
                    'description' => 'Chọn tối đa 4 điện thoại từ trang chi tiết để xem khác biệt thông số.',
                    'actionLabel' => 'Xem điện thoại',
                    'actionUrl' => route('products.index'),
                ])
            </div>
        @else
            <div class="container">
                <div class="page-title-row">
                    <div><span class="eyebrow">Chọn thông minh hơn</span><h1>So sánh điện thoại</h1></div>
                    <a class="btn btn-outline-primary" href="{{ route('products.index') }}"><x-icon name="arrow-left" /> Xem thêm</a>
                </div>
                <div class="compare-scroll panel">
                    <table class="compare-table">
                        <thead>
                            <tr>
                                <th>Thông số</th>
                                @foreach ($products as $product)
                                    <th>
                                        <form method="POST" action="{{ route('compare.toggle', $product) }}" class="inline-form">
                                            @csrf
                                            <button class="compare-remove" aria-label="Bỏ {{ $product->name }} khỏi so sánh"><x-icon name="x" /></button>
                                        </form>
                                        <img src="{{ media_url($product->image) }}" alt="">
                                        <a href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $label => $row)
                                <tr>
                                    <th>{{ $label }}</th>
                                    @foreach ($row['values'] as $value)
                                        <td @class(['is-different' => $row['different']])>{{ $value }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </main>
@endsection
