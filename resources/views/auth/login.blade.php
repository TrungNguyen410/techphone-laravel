@extends('layouts.auth')

@section('title', 'Đăng nhập')

@section('visual')
    <x-icon name="shield" /><h1>Mua sắm công nghệ<br>an tâm mỗi ngày</h1><p>Sản phẩm kiểm định, bảo hành rõ ràng và hỗ trợ tận tâm.</p>
@endsection

@section('content')
    <form class="auth-form" method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <span class="eyebrow">Chào mừng trở lại</span>
        <h1>Đăng nhập tài khoản</h1>
        <p>Tiếp tục mua sắm và quản lý đơn hàng của bạn.</p>
        <label class="input-with-icon">
            <span>Số điện thoại</span>
            <div><x-icon name="phone" /><input name="identifier" value="{{ old('identifier') }}" inputmode="tel" autocomplete="tel" placeholder="0912 345 678" required autofocus></div>
            @error('identifier')<small>{{ $message }}</small>@enderror
        </label>
        <label class="input-with-icon">
            <span>Mật khẩu</span>
            <div>
                <x-icon name="lock" />
                <input type="password" name="password" placeholder="Nhập mật khẩu" autocomplete="current-password" required data-password>
                <button type="button" data-toggle-password aria-label="Hiện mật khẩu"><x-icon name="eye" /></button>
            </div>
        </label>
        <div class="auth-forgot-link">
            <label class="remember-me"><input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập</label>
            <a href="{{ route('password.request') }}">Quên mật khẩu?</a>
        </div>
        <button class="btn btn-primary auth-submit"><x-icon name="log-in" /> Đăng nhập</button>
        @if (app()->environment('local'))
            <div class="mock-accounts">
                <strong>Tài khoản dùng thử (mật khẩu {{ config('services.seed_password') }})</strong>
                <button type="button" data-fill-login="0911111111">Khách hàng: 0911111111</button>
                <button type="button" data-fill-login="0900000000">Quản trị: 0900000000</button>
            </div>
        @endif
        <p class="auth-switch">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a></p>
    </form>
@endsection
