<td><strong>{{ $item->name }}</strong><small class="table-subtext">{{ $item->slug }}</small></td>
<td>{{ $item->products_count + $item->accessories_count }}</td>
<td><span class="status-badge {{ $item->active ? 'status-completed' : 'status-cancelled' }}">{{ $item->active ? 'Hoạt động' : 'Tạm ẩn' }}</span></td>
