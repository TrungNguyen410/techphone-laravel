@extends('layouts.auth')

@section('title', 'Quên mật khẩu')

@section('visual')
    <x-icon name="key" /><h1>Khôi phục tài khoản<br>an toàn bằng OTP</h1><p>Mã chỉ có hiệu lực trong 10 phút và được dùng một lần.</p>
@endsection

@section('content')
    <form class="auth-form" method="POST" action="{{ route('password.otp') }}">
        @csrf
        <span class="eyebrow">Bảo mật tài khoản</span>
        <h1>Quên mật khẩu</h1>
        <p>Nhập số điện thoại tài khoản để nhận mã OTP qua SMS.</p>
        <label class="input-with-icon">
            <span>Số điện thoại tài khoản</span>
            <div><x-icon name="phone" /><input name="identifier" value="{{ old('identifier') }}" inputmode="tel" autocomplete="tel" placeholder="0912 345 678" required autofocus></div>
            @error('identifier')<small>{{ $message }}</small>@enderror
        </label>
        <button class="btn btn-primary auth-submit">Gửi mã OTP</button>
        <p class="auth-switch"><a href="{{ route('login') }}">Quay lại đăng nhập</a></p>
    </form>
@endsection
