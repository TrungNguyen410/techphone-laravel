<div class="empty-state">
    <div class="empty-state-icon"><x-icon :name="$icon ?? 'inbox'" /></div>
    <h3>{{ $title ?? 'Chưa có dữ liệu' }}</h3>
    <p>{{ $description ?? 'Nội dung sẽ xuất hiện tại đây.' }}</p>
    @isset($actionLabel)
        <a class="btn btn-primary" href="{{ $actionUrl ?? route('home') }}">{{ $actionLabel }}</a>
    @endisset
</div>
