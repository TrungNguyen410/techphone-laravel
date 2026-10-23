@extends('layouts.auth')

@section('title', 'Tạo mật khẩu mới')

@php $delivery = session('otp_delivery'); @endphp

@section('visual')
    <x-icon name="key" /><h1>Khôi phục tài khoản<br>an toàn bằng OTP</h1><p>Mã chỉ có hiệu lực trong 10 phút và được dùng một lần.</p>
@endsection

@section('content')
    <form class="auth-form" method="POST" action="{{ route('password.update') }}">
        @csrf
        <span class="eyebrow">Bảo mật tài khoản</span>
        <h1>Tạo mật khẩu mới</h1>
        <p>
            @if (isset($delivery['debug_otp']))
                Đang dùng SMS thử nghiệm cho {{ $delivery['delivery_target'] }}; mã OTP: <strong>{{ $delivery['debug_otp'] }}</strong>.
            @else
                Nhập mã SMS đã gửi đến số điện thoại của bạn (nếu tài khoản tồn tại).
            @endif
        </p>
        <label class="input-with-icon otp-input">
            <span>Mã OTP</span>
            <div><x-icon name="key" /><input name="otp" inputmode="numeric" maxlength="6" pattern="\d{6}" placeholder="••••••" autocomplete="one-time-code" required autofocus data-digits-only></div>
            @error('otp')<small>{{ $message }}</small>@enderror
        </label>
        <label class="input-with-icon">
            <span>Mật khẩu mới</span>
            <div><x-icon name="lock" /><input type="password" name="password" minlength="6" autocomplete="new-password" required></div>
            @error('password')<small>{{ $message }}</small>@enderror
        </label>
        <label class="input-with-icon">
            <span>Xác nhận mật khẩu mới</span>
            <div><x-icon name="lock" /><input type="password" name="password_confirmation" autocomplete="new-password" required></div>
        </label>
        <a class="auth-text-button" href="{{ route('password.request') }}">Gửi lại hoặc sửa số điện thoại</a>
        <button class="btn btn-primary auth-submit">Đặt lại mật khẩu</button>
        <p class="auth-switch"><a href="{{ route('login') }}">Quay lại đăng nhập</a></p>
    </form>
@endsection
