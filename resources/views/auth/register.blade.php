@extends('layouts.auth')

@section('title', 'Đăng ký')
@section('page-class', 'register-page')

@section('visual')
    <x-icon name="user-plus" /><h1>Gia nhập cộng đồng<br>{{ $store['storeName'] }}</h1><p>Theo dõi đơn hàng, nhận ưu đãi và chăm sóc cá nhân hóa.</p>
@endsection

@section('content')
    <form class="auth-form" method="POST" action="{{ route('register.otp') }}">
        @csrf
        <span class="eyebrow">Tạo tài khoản mới</span>
        <h1>Đăng ký thành viên</h1>
        <p>Mã xác minh sẽ được gửi qua SMS đến số điện thoại của bạn.</p>
        <label class="input-with-icon">
            <span>Họ và tên</span>
            <div><x-icon name="user-plus" /><input name="full_name" value="{{ old('full_name') }}" required></div>
            @error('full_name')<small>{{ $message }}</small>@enderror
        </label>
        <label class="input-with-icon">
            <span>Số điện thoại</span>
            <div><x-icon name="phone" /><input name="phone" value="{{ old('phone') }}" inputmode="tel" autocomplete="tel" placeholder="0912 345 678" required></div>
            @error('phone')<small>{{ $message }}</small>@enderror
        </label>
        <div class="auth-two-columns">
            <label class="input-with-icon">
                <span>Mật khẩu</span>
                <div><x-icon name="lock" /><input type="password" name="password" minlength="6" autocomplete="new-password" required></div>
                @error('password')<small>{{ $message }}</small>@enderror
            </label>
            <label class="input-with-icon">
                <span>Xác nhận mật khẩu</span>
                <div><x-icon name="lock" /><input type="password" name="password_confirmation" autocomplete="new-password" required></div>
            </label>
        </div>
        <button class="btn btn-primary auth-submit">Gửi mã xác minh</button>
        <p class="auth-switch">Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a></p>
    </form>
@endsection
