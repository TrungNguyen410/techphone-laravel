@php $expired = $item->end_date->lt(now()->startOfDay()); @endphp
<td><strong class="voucher-code">{{ $item->code }}</strong></td>
<td>{{ App\Models\Voucher::TYPES[$item->type] }}</td>
<td>{{ $item->valueLabel() }}@if ($item->type === 'percent' && $item->max_discount)<small class="table-subtext">Tối đa {{ money($item->max_discount) }}</small>@endif</td>
<td>{{ money($item->min_order) }}</td>
<td>{{ $item->used }}/{{ $item->quantity ?: '∞' }}</td>
<td>{{ format_date($item->start_date) }} – {{ format_date($item->end_date) }}</td>
<td>
    @if ($expired)
        <span class="status-badge status-cancelled">Hết hạn</span>
    @else
        <span class="status-badge {{ $item->active ? 'status-completed' : 'status-cancelled' }}">{{ $item->active ? 'Hoạt động' : 'Tạm dừng' }}</span>
    @endif
</td>
