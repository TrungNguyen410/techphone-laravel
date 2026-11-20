<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\VietnamesePhoneRule;
use App\Support\VietnamesePhone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Đăng ký bằng số điện thoại + mật khẩu, không gửi mã SMS (gửi SMS thật đều tốn phí).
 * Số điện thoại là tên đăng nhập nhưng chưa được xác minh. OTP chỉ còn dùng cho Quên mật khẩu.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', new VietnamesePhoneRule],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'required' => 'Vui lòng nhập thông tin này',
            'full_name.max' => 'Họ và tên tối đa 100 ký tự',
            'password.min' => 'Mật khẩu cần ít nhất 6 ký tự',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp',
        ]);

        $phone = VietnamesePhone::normalize($data['phone']);
        if (User::where('phone', $phone)->exists()) {
            throw new BusinessException('Số điện thoại này đã được đăng ký', 409);
        }

        try {
            // Số điện thoại của tài khoản đã xóa được giải phóng để người khác (hoặc chính chủ) đăng ký lại.
            User::onlyTrashed()->where('phone', $phone)->update(['phone' => null]);

            User::create([
                'full_name' => trim($data['full_name']),
                'phone' => $phone,
                'password' => $data['password'],
                'role' => 'customer',
                'status' => 'active',
                'auth_provider' => 'phone',
                'phone_verified_at' => null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Bấm đăng ký 2 lần cùng lúc: request còn lại đã tạo tài khoản.
            throw new BusinessException('Số điện thoại này đã được đăng ký', 409);
        }

        return redirect()->route('login')->withInput(['identifier' => $phone])->with('toast', [
            'type' => 'success',
            'message' => 'Đăng ký thành công. Vui lòng đăng nhập!',
        ]);
    }
}
