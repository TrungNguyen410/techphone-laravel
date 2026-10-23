@extends('layouts.auth')

@section('title', 'Xác minh số điện thoại')
@section('page-class', 'register-page')

@php
    $delivery = session('otp_delivery', ['delivery_target' => App\Support\VietnamesePhone::mask($phone)]);
@endphp

@section('visual')
    <x-icon name="user-plus" /><h1>Gia nhập cộng đồng<br>{{ $store['storeName'] }}</h1><p>Theo dõi đơn hàng, nhận ưu đãi và chăm sóc cá nhân hóa.</p>
@endsection

@section('content')
    <form class="auth-form" method="POST" action="{{ route('register.verify.submit') }}">
        @csrf
        <span class="eyebrow">Xác minh qua SMS</span>
        <h1>Nhập mã OTP</h1>
        <p>
            @if (isset($delivery['debug_otp']))
                Đang dùng SMS thử nghiệm cho {{ $delivery['delivery_target'] }}; mã OTP: <strong>{{ $delivery['debug_otp'] }}</strong>.
            @else
                Mã gồm 6 chữ số đã được gửi qua SMS đến {{ $delivery['delivery_target'] }}. Mã có hiệu lực trong 10 phút.
            @endif
        </p>
        <label class="input-with-icon otp-input">
            <span>Mã OTP</span>
            <div><x-icon name="key" /><input name="otp" inputmode="numeric" maxlength="6" pattern="\d{6}" placeholder="••••••" autocomplete="one-time-code" required autofocus data-digits-only></div>
            @error('otp')<small>{{ $message }}</small>@enderror
        </label>
        <a class="auth-text-button" href="{{ route('register') }}">Quay lại sửa thông tin</a>
        <button class="btn btn-primary auth-submit">Xác minh và đăng ký</button>
        <p class="auth-switch">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
    </form>
@endsection
