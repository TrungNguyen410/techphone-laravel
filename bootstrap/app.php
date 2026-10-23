<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [AuthenticateSession::class, EnsureAccountIsActive::class]);
        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin()
            ? route('admin.dashboard')
            : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Lỗi nghiệp vụ (hết hàng, voucher sai...) là tình huống bình thường, không ghi vào log lỗi.
        $exceptions->dontReport(BusinessException::class);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
