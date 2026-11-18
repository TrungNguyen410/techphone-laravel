<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\FirebaseTokenVerifier;
use App\Services\ShopperPreferences;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Đăng nhập (hoặc tạo tài khoản khách hàng) bằng Google/Facebook. Firebase chỉ chứng minh danh tính,
 * máy chủ kiểm tra ID token rồi tạo phiên đăng nhập Laravel như đăng nhập bằng số điện thoại.
 */
class SocialLoginController extends Controller
{
    /** Mã nhà cung cấp của Firebase → cột users.auth_provider */
    private const PROVIDERS = ['google.com' => 'google', 'facebook.com' => 'facebook'];

    public function __invoke(Request $request, FirebaseTokenVerifier $verifier, ShopperPreferences $preferences): JsonResponse
    {
        $request->validate(['id_token' => ['required', 'string', 'max:4096']]);

        $claims = $verifier->verify($request->string('id_token'), array_keys(self::PROVIDERS));
        $user = $this->findOrCreate($claims);

        if ($user->status === 'locked') {
            throw new BusinessException('Tài khoản đã bị khóa', 403);
        }
        if ($user->status !== 'active') {
            throw new BusinessException('Tài khoản đang ngừng hoạt động', 403);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $preferences->mergeGuestWishlist($user);
        session()->flash('toast', ['type' => 'success', 'message' => 'Chào mừng '.$user->full_name]);

        $target = redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('home'))->getTargetUrl();

        return response()->json(['redirect' => ! $user->isAdmin() && Str::contains($target, '/admin') ? route('home') : $target]);
    }

    /**
     * Tài khoản được nhận theo Firebase uid. Không tự gộp với tài khoản có sẵn cùng email (tránh chiếm tài khoản).
     *
     * @param  array<string, mixed>  $claims
     */
    private function findOrCreate(array $claims): User
    {
        $uid = (string) $claims['sub'];
        if ($user = User::where('firebase_uid', $uid)->first()) {
            return $user;
        }

        $email = mb_strtolower(trim((string) ($claims['email'] ?? '')));
        $conflict = 'Email này đã gắn với một tài khoản khác. Vui lòng đăng nhập bằng số điện thoại và mật khẩu.';
        if ($email !== '' && User::withTrashed()->where('email', $email)->exists()) {
            throw new BusinessException($conflict, 409);
        }

        $name = trim((string) ($claims['name'] ?? '')) ?: trim(Str::before($email, '@'));

        try {
            return User::create([
                'full_name' => mb_substr($name !== '' ? $name : 'Khách hàng', 0, 120),
                'email' => $email !== '' ? $email : null,
                'phone' => null,
                'password' => null,
                'role' => 'customer',
                'status' => 'active',
                'avatar' => is_string($claims['picture'] ?? null) ? mb_substr($claims['picture'], 0, 255) : '',
                'auth_provider' => self::PROVIDERS[$claims['firebase']['sign_in_provider']],
                'firebase_uid' => $uid,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Bấm hai lần: request còn lại đã tạo tài khoản.
            return User::where('firebase_uid', $uid)->first() ?? throw new BusinessException($conflict, 409);
        }
    }
}
