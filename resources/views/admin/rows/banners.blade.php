<td><div class="table-banner"><img src="{{ media_url($item->image) }}" alt=""><span><strong>{{ $item->title }}</strong><small>{{ $item->description }}</small></span></div></td>
<td>{{ $item->link }}</td>
<td>{{ $item->position }}</td>
<td><span class="status-badge {{ $item->active ? 'status-completed' : 'status-cancelled' }}">{{ $item->active ? 'Hiển thị' : 'Đã ẩn' }}</span></td>
