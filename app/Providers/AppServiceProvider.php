<?php

namespace App\Providers;

use App\Models\Contact;
use App\Services\Cart\CartService;
use App\Services\SettingService;
use App\Services\ShopperPreferences;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Mỗi request dùng chung một đối tượng giỏ hàng / lựa chọn cá nhân.
        $this->app->scoped(CartService::class);
        $this->app->scoped(ShopperPreferences::class);
        $this->app->singleton(SettingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        // Thông tin cửa hàng (tên, hotline, logo...) dùng ở mọi giao diện.
        View::composer('*', function ($view) {
            $view->with('store', app(SettingService::class)->all());
        });

        View::composer('partials.header', function ($view) {
            $view->with([
                'cartCount' => app(CartService::class)->count(),
                'wishlistCount' => count(app(ShopperPreferences::class)->wishlist()),
            ]);
        });

        View::composer('admin.partials.header', function ($view) {
            $view->with('newContacts', Contact::where('status', 'new')->count());
        });

        $this->configureRateLimiting();
    }

    /**
     * Giới hạn tần suất cho đăng nhập, gửi OTP và tra cứu đơn (chống dò mật khẩu / spam SMS).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-id:'.mb_strtolower((string) $request->input('identifier'))),
        ]);

        RateLimiter::for('otp-request', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('otp-ip:'.$request->ip()),
            Limit::perMinutes(10, 3)->by('otp-phone:'.preg_replace('/\D/', '', (string) $request->input('phone', $request->input('identifier')))),
            Limit::perDay(10)->by('otp-phone-day:'.preg_replace('/\D/', '', (string) $request->input('phone', $request->input('identifier')))),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(10, 10)->by('otp-verify:'.$request->ip()));

        RateLimiter::for('order-lookup', fn (Request $request) => [
            Limit::perMinute(20)->by('lookup-ip:'.$request->ip()),
            Limit::perMinute(5)->by('lookup-order:'.strtoupper((string) $request->input('order_number'))),
        ]);
    }
}
