@extends('layouts.store')

@section('title', 'Liên hệ')

@section('content')
    <main class="page-shell contact-page">
        <div class="container">
            <div class="page-hero">
                <div><span>Chúng tôi luôn lắng nghe</span><h1>Liên hệ {{ $store['storeName'] }}</h1><p>Cần tư vấn sản phẩm, bảo hành hay đơn hàng? Đội ngũ hỗ trợ luôn sẵn sàng.</p></div>
                <x-icon name="send" />
            </div>
            <div class="contact-cards">
                <div><x-icon name="phone" /><span><small>Hotline</small><strong>{{ $store['hotline'] }}</strong><p>8:00 - 22:00 mỗi ngày</p></span></div>
                <div><x-icon name="mail" /><span><small>Email</small><strong>{{ $store['email'] }}</strong><p>Phản hồi trong 24 giờ</p></span></div>
                <div><x-icon name="map-pin" /><span><small>Cửa hàng</small><strong>{{ $store['address'] }}</strong><p>Đón tiếp trực tiếp tại cửa hàng</p></span></div>
                <div><x-icon name="clock" /><span><small>Giờ mở cửa</small><strong>8:00 - 21:30</strong><p>Thứ Hai đến Chủ Nhật</p></span></div>
            </div>
            <div class="contact-layout">
                <form class="panel contact-form" method="POST" action="{{ route('contact.store') }}">
                    @csrf
                    <h2>Gửi yêu cầu hỗ trợ</h2><p>Điền thông tin bên dưới, chúng tôi sẽ liên hệ lại với bạn.</p>
                    <div class="form-grid">
                        @foreach ([
                            ['full_name', 'Họ và tên *', ''], ['phone', 'Số điện thoại *', ''], ['email', 'Email *', 'full'], ['subject', 'Chủ đề *', 'full'],
                        ] as [$name, $label, $width])
                            <label class="form-field {{ $width }}">
                                <span>{{ $label }}</span>
                                <input name="{{ $name }}" value="{{ old($name, $name === 'full_name' ? auth()->user()?->full_name : ($name === 'phone' ? auth()->user()?->phone : ($name === 'email' ? auth()->user()?->email : ''))) }}" @error($name) aria-invalid="true" @enderror>
                                @error($name)<small>{{ $message }}</small>@enderror
                            </label>
                        @endforeach
                        <label class="form-field full">
                            <span>Nội dung *</span>
                            <textarea name="message" rows="5" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                            @error('message')<small>{{ $message }}</small>@enderror
                        </label>
                    </div>
                    <button class="btn btn-primary"><x-icon name="send" /> Gửi liên hệ</button>
                </form>
                <div class="map-placeholder">
                    <x-icon name="map-pin" />
                    <strong>{{ $store['storeName'] }} Flagship Store</strong>
                    <span>{{ $store['address'] }}</span>
                    <a href="https://maps.google.com/?q={{ urlencode($store['address']) }}" target="_blank" rel="noreferrer">Mở Google Maps</a>
                </div>
            </div>
            <section class="faq-section">
                <div class="section-heading"><div><span>Hỗ trợ nhanh</span><h2>Câu hỏi thường gặp</h2></div></div>
                <div class="faq-list">
                    @foreach ($faqs as [$question, $answer])
                        <article @class(['open' => $loop->first]) data-faq>
                            <button type="button" data-faq-toggle><strong>{{ $question }}</strong><x-icon name="chevron-down" /></button>
                            <p>{{ $answer }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>
    </main>
@endsection
