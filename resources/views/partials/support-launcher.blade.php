<div class="support-launcher" data-support-launcher>
    <div class="support-panel panel" aria-label="Kênh hỗ trợ" hidden>
        <div><strong>Liên hệ {{ $store['storeName'] }}</strong><button type="button" aria-label="Đóng hỗ trợ" data-support-toggle><x-icon name="x" /></button></div>
        <a href="tel:{{ preg_replace('/\s/', '', $store['hotline']) }}"><x-icon name="phone" /><span><strong>Gọi hotline</strong><small>{{ $store['hotline'] }}</small></span></a>
        <a href="mailto:{{ $store['email'] }}"><x-icon name="mail" /><span><strong>Gửi email</strong><small>{{ $store['email'] }}</small></span></a>
        @if (! empty($store['zaloUrl']))
            <a href="{{ $store['zaloUrl'] }}" target="_blank" rel="noreferrer"><x-icon name="message-circle" /><span><strong>Mở Zalo</strong><small>Kênh do cửa hàng cấu hình</small></span></a>
        @endif
    </div>
    <button type="button" class="support-toggle" aria-expanded="false" aria-label="Mở kênh hỗ trợ" data-support-toggle>
        <span data-support-icon="open"><x-icon name="message-circle" /></span>
        <span data-support-icon="close" hidden><x-icon name="x" /></span>
        <span data-support-label>Hỗ trợ</span>
    </button>
</div>
