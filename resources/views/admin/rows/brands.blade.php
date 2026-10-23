<td>
    <div class="table-product">
        @if ($item->logo)
            <img src="{{ media_url($item->logo) }}" alt="">
        @else
            <span class="brand-mark">{{ mb_substr($item->name, 0, 1) }}</span>
        @endif
        <span><strong>{{ $item->name }}</strong><small>{{ $item->slug }}</small></span>
    </div>
</td>
<td>{{ $item->products_count + $item->accessories_count }}</td>
<td><span class="status-badge {{ $item->active ? 'status-completed' : 'status-cancelled' }}">{{ $item->active ? 'Hoạt động' : 'Tạm ẩn' }}</span></td>
