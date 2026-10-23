<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Đăng xuất ngay những phiên của tài khoản vừa bị admin khóa hoặc ngừng hoạt động.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('toast', [
                'type' => 'error',
                'message' => $user->status === 'locked' ? 'Tài khoản đã bị khóa' : 'Tài khoản đang ngừng hoạt động',
            ]);
        }

        return $next($request);
    }
}
