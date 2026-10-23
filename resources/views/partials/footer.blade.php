<footer class="site-footer">
    <div class="container footer-grid footer-masthead">
        <div class="footer-about">
            @include('partials.brand', ['class' => 'brand-light'])
            <p>Hệ thống bán lẻ điện thoại và phụ kiện chính hãng, tận tâm từ tư vấn đến hậu mãi.</p>
            <div class="social-links">
                <a href="{{ $store['facebook'] }}" target="_blank" rel="noreferrer" aria-label="Facebook"><x-icon name="facebook" /></a>
                <a href="{{ $store['instagram'] }}" target="_blank" rel="noreferrer" aria-label="Instagram"><x-icon name="instagram" /></a>
                <a href="{{ $store['youtube'] }}" target="_blank" rel="noreferrer" aria-label="Youtube"><x-icon name="youtube" /></a>
            </div>
        </div>
        <nav class="footer-link-rail" aria-label="Chính sách và hỗ trợ">
            <a href="{{ route('contact.index') }}">Cửa hàng &amp; liên hệ</a>
            <a href="{{ route('orders.lookup') }}">Tra cứu đơn</a>
            <a href="{{ route('account.index', ['tab' => 'orders']) }}">Đơn hàng của tôi</a>
            <a href="{{ route('policies.show', 'warranty') }}">Bảo hành</a>
            <a href="{{ route('policies.show', 'returns') }}">Đổi trả</a>
            <a href="{{ route('policies.show', 'shipping') }}">Vận chuyển</a>
            <a href="{{ route('policies.show', 'payment') }}">Thanh toán</a>
        </nav>
        <div class="footer-contact-line">
            <span><x-icon name="map-pin" /> {{ $store['address'] }}</span>
            <a href="tel:{{ preg_replace('/\s/', '', $store['hotline']) }}"><x-icon name="phone" /> {{ $store['hotline'] }}</a>
            <a href="mailto:{{ $store['email'] }}"><x-icon name="mail" /> {{ $store['email'] }}</a>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">© {{ now()->year }} {{ $store['storeName'] }}. Bảo lưu mọi quyền.</div>
    </div>
</footer>
