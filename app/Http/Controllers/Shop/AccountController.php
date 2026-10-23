<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['profile', 'orders', 'password'], true) ? $request->query('tab') : 'profile';
        $orders = $user->orders()->with('items')->latest()->get();

        return view('shop.account', [
            'user' => $user,
            'tab' => $tab,
            'orders' => $orders,
            'selectedOrder' => $request->filled('order') ? $orders->firstWhere('id', (int) $request->query('order')) : null,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ], ['full_name.required' => 'Vui lòng nhập thông tin này']);

        $request->user()->update(['full_name' => trim($data['full_name']), 'address' => trim((string) ($data['address'] ?? ''))]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Đã cập nhật thông tin cá nhân']);
    }

    /**
     * Đổi mật khẩu: kiểm tra mật khẩu cũ và đăng xuất mọi phiên đăng nhập khác.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password.min' => 'Mật khẩu mới cần ít nhất 6 ký tự',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp',
        ]);

        $user = $request->user();
        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không chính xác']);
        }

        // Lưu mật khẩu mới rồi vô hiệu hóa các phiên đăng nhập trên thiết bị khác.
        $user->update(['password' => $request->input('password')]);
        Auth::logoutOtherDevices($request->input('password'));

        return redirect()->route('account.index', ['tab' => 'password'])
            ->with('toast', ['type' => 'success', 'message' => 'Đổi mật khẩu thành công']);
    }
}
