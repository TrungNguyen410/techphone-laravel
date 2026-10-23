@php
    $toasts = array_values(array_filter([session('toast'), session('toast_extra')]));
    // Lỗi kiểm tra dữ liệu (validation) cũng được hiển thị thành thông báo.
    if ($errors->any() && ! session('toast')) {
        $toasts[] = ['type' => 'error', 'message' => $errors->first()];
    }
@endphp
<div class="toast-stack" aria-live="polite" data-toast-stack>
    @foreach ($toasts as $toast)
        <div class="app-toast {{ $toast['type'] ?? 'info' }}" role="status" data-toast>
            <x-icon :name="match ($toast['type'] ?? 'info') { 'success' => 'check-circle', 'error' => 'alert-circle', default => 'alert-circle' }" />
            <span>{{ $toast['message'] }}</span>
            <button type="button" aria-label="Đóng thông báo" data-toast-close><x-icon name="x" /></button>
        </div>
    @endforeach
</div>
