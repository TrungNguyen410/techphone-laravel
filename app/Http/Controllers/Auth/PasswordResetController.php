<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Support\VietnamesePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Quên mật khẩu: nhận OTP qua SMS rồi đặt mật khẩu mới.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(): View
    {
        return view('auth.forgot-password');
    }

    public function requestOtp(Request $request): RedirectResponse
    {
        $request->validate(['identifier' => ['required', 'string']], ['identifier.required' => 'Vui lòng nhập số điện thoại']);

        $phone = VietnamesePhone::normalize($request->input('identifier'));
        $user = $phone ? User::where('phone', $phone)->first() : null;
        $delivery = ['delivery_target' => VietnamesePhone::mask($request->input('identifier'))];

        // Không tiết lộ số điện thoại có tài khoản hay không.
        if ($user) {
            $delivery = $this->otp->send($user->phone, 'password-reset', ['user_id' => $user->id]);
        }

        $request->session()->put('reset_phone', $phone ?? $request->input('identifier'));

        return redirect()->route('password.reset')->with('otp_delivery', $delivery)->with('toast', [
            'type' => 'success',
            'message' => isset($delivery['debug_otp'])
                ? 'Mã OTP thử nghiệm: '.$delivery['debug_otp']
                : 'Nếu tài khoản tồn tại, mã OTP đã được gửi',
        ]);
    }

    public function showReset(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('reset_phone')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'otp.*' => 'Mã OTP phải gồm 6 chữ số',
            'password.min' => 'Mật khẩu mới cần ít nhất 6 ký tự',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp',
        ]);

        $phone = $request->session()->get('reset_phone');
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if (! $user) {
            throw new BusinessException('Mã OTP không hợp lệ hoặc đã hết hạn', 400);
        }

        $this->otp->verify($user->phone, 'password-reset', $request->input('otp'));

        // Đổi mật khẩu và hủy "ghi nhớ đăng nhập" + mọi phiên đang mở của tài khoản.
        $user->forceFill(['password' => $request->input('password'), 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $request->session()->forget('reset_phone');

        return redirect()->route('login')->with('toast', ['type' => 'success', 'message' => 'Đặt lại mật khẩu thành công']);
    }
}
