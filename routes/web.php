<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Shop;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cửa hàng (khách hàng)
|--------------------------------------------------------------------------
*/
Route::get('/', Shop\HomeController::class)->name('home');

Route::get('/products', [Shop\ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [Shop\ProductController::class, 'show'])->name('products.show');
Route::get('/accessories', [Shop\AccessoryController::class, 'index'])->name('accessories.index');
Route::get('/accessories/{accessory}', [Shop\AccessoryController::class, 'show'])->name('accessories.show');
Route::get('/search/suggest', [Shop\ProductController::class, 'suggest'])->name('search.suggest');

Route::get('/compare', [Shop\PreferenceController::class, 'compare'])->name('compare.index');
Route::post('/compare/{product}', [Shop\PreferenceController::class, 'toggleCompare'])->name('compare.toggle');
Route::get('/favorites', [Shop\PreferenceController::class, 'favorites'])->name('favorites.index');
Route::post('/wishlist/{type}/{id}', [Shop\PreferenceController::class, 'toggleWishlist'])
    ->whereIn('type', ['product', 'accessory'])->whereNumber('id')->name('wishlist.toggle');

Route::controller(Shop\CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/add/{type}/{id}', 'add')->whereIn('type', ['product', 'accessory'])->whereNumber('id')->name('add');
    Route::patch('/items/{key}', 'update')->name('update');
    Route::delete('/items/{key}', 'remove')->name('remove');
    Route::delete('/', 'clear')->name('clear');
    Route::post('/voucher', 'applyVoucher')->name('voucher.apply');
    Route::delete('/voucher', 'removeVoucher')->name('voucher.remove');
});

Route::get('/order-lookup', [Shop\OrderController::class, 'lookup'])->middleware('throttle:order-lookup')->name('orders.lookup');
Route::get('/reviews', [Shop\ReviewController::class, 'index'])->name('reviews.index');
Route::get('/contact', [Shop\ContactController::class, 'index'])->name('contact.index');
Route::post('/contact', [Shop\ContactController::class, 'store'])->middleware('throttle:10,1')->name('contact.store');
Route::get('/policies/{type}', Shop\PolicyController::class)->name('policies.show');
Route::get('/sitemap.xml', Shop\SitemapController::class)->name('sitemap');

Route::get('/payment/vnpay/return', [Shop\PaymentController::class, 'vnpayReturn'])->name('payments.vnpay.return');
Route::get('/payment/vnpay/ipn', [Shop\PaymentController::class, 'vnpayIpn'])->name('payments.vnpay.ipn');

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [Shop\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [Shop\CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/order-success/{order}', [Shop\OrderController::class, 'success'])->name('orders.success');
    Route::post('/orders/{order}/cancel', [Shop\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/reorder', [Shop\OrderController::class, 'reorder'])->name('orders.reorder');
    Route::post('/orders/{order}/pay', [Shop\PaymentController::class, 'retry'])->name('orders.pay');

    Route::post('/reviews', [Shop\ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/account', [Shop\AccountController::class, 'index'])->name('account.index');
    Route::put('/account/profile', [Shop\AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [Shop\AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Xác thực: đăng nhập, đăng ký bằng OTP, quên mật khẩu
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.attempt');

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'requestOtp'])->middleware('throttle:otp-request')->name('register.otp');
    Route::get('/register/verify', [RegisterController::class, 'showVerify'])->name('register.verify');
    Route::post('/register/verify', [RegisterController::class, 'verify'])->middleware('throttle:otp-verify')->name('register.verify.submit');

    Route::get('/forgot-password', [PasswordResetController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'requestOtp'])->middleware('throttle:otp-request')->name('password.otp');
    Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:otp-verify')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Quản trị
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');

    Route::resource('products', Admin\ProductController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('accessories', Admin\AccessoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('categories', Admin\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('brands', Admin\BrandController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('banners', Admin\BannerController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('vouchers', Admin\VoucherController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::controller(Admin\OrderController::class)->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::patch('/{order}/status', 'updateStatus')->name('status');
        Route::put('/{order}/shipping', 'updateShipping')->name('shipping');
        Route::put('/{order}/payment', 'reconcilePayment')->name('payment');
        Route::delete('/{order}', 'destroy')->name('destroy');
    });

    Route::get('/customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::patch('/customers/{user}/lock', [Admin\CustomerController::class, 'toggleLock'])->name('customers.lock');

    Route::get('/contacts', [Admin\ContactController::class, 'index'])->name('contacts.index');
    Route::put('/contacts/{contact}', [Admin\ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [Admin\ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/{status}', [Admin\ReviewController::class, 'moderate'])
        ->whereIn('status', ['approved', 'rejected'])->name('reviews.moderate');
    Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
});
