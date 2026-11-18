<td><strong>{{ $item->title }}</strong><small class="table-subtext">{{ \Illuminate\Support\Str::limit($item->content, 70) }}</small></td>
<td>{{ $item->categoryLabel() }}</td>
<td><span class="table-keywords">{{ \Illuminate\Support\Str::limit($item->keywords_text, 60) ?: '---' }}</span></td>
<td><span class="status-badge {{ $item->active ? 'status-completed' : 'status-cancelled' }}">{{ $item->active ? 'Đang dùng' : 'Tạm tắt' }}</span></td>
