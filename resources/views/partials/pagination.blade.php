{{-- Phân trang giống component Pagination của bản React: Trước · 1 … 4 5 6 … 12 · Sau --}}
@if ($paginator->hasPages())
    @php
        $last = $paginator->lastPage();
        $current = $paginator->currentPage();
        $start = max(2, $current - 2);
        $end = min($last - 1, $current + 2);
        if ($end - $start < 4) {
            if ($start === 2) { $end = min($last - 1, $start + 4); }
            if ($end === $last - 1) { $start = max(2, $end - 4); }
        }
        $pages = $last <= 7
            ? range(1, $last)
            : array_merge([1], $start > 2 ? ['…'] : [], range($start, $end), $end < $last - 1 ? ['…'] : [], [$last]);
    @endphp
    <nav class="pagination-wrap" aria-label="Phân trang">
        @if ($paginator->onFirstPage())
            <button disabled>Trước</button>
        @else
            <a class="page-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Trước</a>
        @endif
        @foreach ($pages as $page)
            @if ($page === '…')
                <span aria-hidden="true">…</span>
            @elseif ($page === $current)
                <button class="active" aria-current="page" aria-label="Trang {{ $page }}">{{ $page }}</button>
            @else
                <a class="page-button" href="{{ $paginator->url($page) }}" aria-label="Trang {{ $page }}">{{ $page }}</a>
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a class="page-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau</a>
        @else
            <button disabled>Sau</button>
        @endif
    </nav>
@endif
