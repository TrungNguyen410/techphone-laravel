@if ($items->isEmpty())
    @include('partials.empty-state', ['title' => 'Không tìm thấy sản phẩm', 'description' => 'Hãy thử thay đổi từ khóa hoặc bộ lọc.'])
@else
    <div class="product-grid">
        @foreach ($items as $item)
            @include('partials.product-card', ['item' => $item])
        @endforeach
    </div>
@endif
