<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShippingAddressRequest;
use App\Services\ShippingService;
use App\Services\VoucherService;
use App\Support\ShippingAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request, VoucherService $vouchers): View
    {
        $user = $request->user();
        // Tài khoản Google/Facebook không có mật khẩu nên không có mục đổi mật khẩu.
        $tabs = $user->isSocialAccount()
            ? ['profile', 'address', 'orders', 'vouchers']
            : ['profile', 'address', 'orders', 'vouchers', 'password'];
        $tab = in_array($request->query('tab'), $tabs, true) ? $request->query('tab') : 'profile';
        $orders = $user->orders()->with('items')->latest()->get();

        return view('shop.account', [
            'user' => $user,
            'tab' => $tab,
            'tabs' => $tabs,
            'orders' => $orders,
            'selectedOrder' => $request->filled('order') ? $orders->firstWhere('id', (int) $request->query('order')) : null,
            'vouchers' => $tab === 'vouchers' ? $vouchers->giftsFor($user) : collect(),
            'giftCount' => $user->vouchers()->count(),
            'addressValues' => $user->shipping_address
                ? ShippingAddress::from($user->shipping_address)
                : ['full_name' => $user->full_name, 'phone' => (string) $user->phone, 'email' => (string) $user->email, 'address' => '', 'province' => '', 'ward' => ''],
            'provinces' => ShippingService::PROVINCES,
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

    /** Địa chỉ nhận hàng mặc định, được điền sẵn ở bước thanh toán. */
    public function updateAddress(ShippingAddressRequest $request): RedirectResponse
    {
        $request->user()->forceFill(['shipping_address' => ShippingAddress::from($request->validated())])->save();

        return redirect()->route('account.index', ['tab' => 'address'])
            ->with('toast', ['type' => 'success', 'message' => 'Đã lưu địa chỉ nhận hàng']);
    }

    /**
     * Đổi mật khẩu: kiểm tra mật khẩu cũ và đăng xuất mọi phiên đăng nhập khác.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        abort_if($request->user()->isSocialAccount(), 404);

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password.min' => 'Mật khẩu mới cần ít nhất 6 ký tự',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp',
        ]);

        $user = $request->user();
        if (! Hash::check($request->input('current_password'), (string) $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không chính xác']);
        }

        // Lưu mật khẩu mới rồi vô hiệu hóa các phiên đăng nhập trên thiết bị khác.
        $user->update(['password' => $request->input('password')]);
        Auth::logoutOtherDevices($request->input('password'));

        return redirect()->route('account.index', ['tab' => 'password'])
            ->with('toast', ['type' => 'success', 'message' => 'Đổi mật khẩu thành công']);
    }
}
