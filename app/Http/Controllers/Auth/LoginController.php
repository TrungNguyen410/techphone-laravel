<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ShopperPreferences;
use App\Support\VietnamesePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(Request $request): View
    {
        // ?redirect=/duong-dan: quay lại trang đang xem sau khi đăng nhập (chỉ nhận đường dẫn nội bộ).
        $redirect = (string) $request->query('redirect');
        if (str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            $request->session()->put('url.intended', url($redirect));
        }

        return view('auth.login');
    }

    /**
     * Đăng nhập bằng số điện thoại (hoặc email). Thông báo lỗi chung để không lộ tài khoản có tồn tại hay không.
     */
    public function login(Request $request, ShopperPreferences $preferences): RedirectResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ], ['required' => 'Vui lòng nhập đầy đủ thông tin']);

        $user = $this->findByIdentifier($request->string('identifier'));

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['identifier' => 'Thông tin đăng nhập không chính xác']);
        }
        if ($user->status === 'locked') {
            throw ValidationException::withMessages(['identifier' => 'Tài khoản đã bị khóa']);
        }
        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['identifier' => 'Tài khoản đang ngừng hoạt động']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $preferences->mergeGuestWishlist($user);

        $intended = $user->isAdmin() ? route('admin.dashboard') : route('home');
        $target = redirect()->intended($intended)->getTargetUrl();
        // Khách hàng không được chuyển vào khu vực quản trị.
        if (! $user->isAdmin() && Str::contains($target, '/admin')) {
            $target = route('home');
        }

        return redirect()->to($target)->with('toast', ['type' => 'success', 'message' => 'Chào mừng '.$user->full_name]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function findByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);
        $phone = VietnamesePhone::normalize($identifier);

        if ($phone) {
            return User::where('phone', $phone)->first();
        }

        return str_contains($identifier, '@') ? User::where('email', mb_strtolower($identifier))->first() : null;
    }
}
