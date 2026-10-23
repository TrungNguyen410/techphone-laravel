{{-- Ô tìm kiếm + nút bộ lọc (mobile) + sắp xếp; các bộ lọc khác được giữ qua input ẩn --}}
<div class="catalog-toolbar">
    <form class="search-box" method="GET" action="{{ $action }}">
        <x-icon name="search" />
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}">
        @foreach (collect($filters)->except(['q', 'page'])->filter() as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        @if (! empty($filters['q']))
            <a href="{{ $action.'?'.http_build_query(collect($filters)->except(['q', 'page'])->filter()->all()) }}" aria-label="Xóa tìm kiếm" class="search-clear"><x-icon name="x" /></a>
        @endif
    </form>
    <button class="mobile-filter-button" type="button" data-filter-open><x-icon name="filter" /> Bộ lọc</button>
    <form class="sort-control" method="GET" action="{{ $action }}">
        @foreach (collect($filters)->except(['sort', 'page'])->filter() as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <label class="sort-control">
            <span>Sắp xếp:</span>
            <select name="sort" data-auto-submit>
                @foreach (App\Services\CatalogService::SORTS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </form>
</div>
