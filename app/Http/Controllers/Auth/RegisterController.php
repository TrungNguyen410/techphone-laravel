<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\VietnamesePhoneRule;
use App\Services\Auth\OtpService;
use App\Support\VietnamesePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Đăng ký 2 bước: nhập thông tin → nhận OTP qua SMS → xác minh để tạo tài khoản.
 */
class RegisterController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(): View
    {
        return view('auth.register');
    }

    public function requestOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', new VietnamesePhoneRule],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'required' => 'Vui lòng nhập thông tin này',
            'password.min' => 'Mật khẩu cần ít nhất 6 ký tự',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp',
        ]);

        $phone = VietnamesePhone::normalize($data['phone']);
        if (User::withTrashed()->where('phone', $phone)->exists()) {
            throw new BusinessException('Số điện thoại này đã được đăng ký', 409);
        }

        // Thông tin đăng ký được giữ trong OTP (mật khẩu đã băm) và chỉ tạo tài khoản sau khi xác minh.
        $delivery = $this->otp->send($phone, 'register', [
            'full_name' => trim($data['full_name']),
            'phone' => $phone,
            'password' => Hash::make($data['password']),
        ]);

        $request->session()->put('register_phone', $phone);

        return redirect()->route('register.verify')->with('otp_delivery', $delivery)->with('toast', [
            'type' => 'success',
            'message' => isset($delivery['debug_otp']) ? 'Mã OTP SMS thử nghiệm: '.$delivery['debug_otp'] : 'Đã gửi mã OTP qua SMS',
        ]);
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('register_phone')) {
            return redirect()->route('register');
        }

        return view('auth.register-verify', ['phone' => $request->session()->get('register_phone')]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['otp' => ['required', 'digits:6']], ['otp.*' => 'Vui lòng nhập mã OTP gồm 6 chữ số']);
        $phone = $request->session()->get('register_phone') ?? abort(419);

        $verification = $this->otp->verify($phone, 'register', $request->input('otp'));
        $payload = $verification->payload;

        if (User::withTrashed()->where('phone', $payload['phone'])->exists()) {
            throw new BusinessException('Số điện thoại này đã được đăng ký', 409);
        }

        // Mật khẩu trong payload đã được băm từ bước trước (cast "hashed" sẽ không băm lại).
        User::create([
            'full_name' => $payload['full_name'],
            'phone' => $payload['phone'],
            'password' => $payload['password'],
            'role' => 'customer',
            'status' => 'active',
            'phone_verified_at' => now(),
        ]);
        $request->session()->forget('register_phone');

        return redirect()->route('login')->with('toast', [
            'type' => 'success',
            'message' => 'Xác minh số điện thoại và đăng ký thành công. Vui lòng đăng nhập!',
        ]);
    }
}
