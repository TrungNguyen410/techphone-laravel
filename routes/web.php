<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialLoginController;
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
Route::get('/privacy', [Shop\PolicyController::class, 'privacy'])->name('privacy');
Route::get('/xoa-du-lieu', [Shop\PolicyController::class, 'dataDeletion'])->name('data-deletion');
Route::get('/sitemap.xml', Shop\SitemapController::class)->name('sitemap');

Route::get('/payment/vnpay/return', [Shop\PaymentController::class, 'vnpayReturn'])->name('payments.vnpay.return');
Route::get('/payment/vnpay/ipn', [Shop\PaymentController::class, 'vnpayIpn'])->name('payments.vnpay.ipn');
// Webhook SePay (máy chủ SePay gọi khi có tiền chuyển vào tài khoản), không dùng CSRF - xem bootstrap/app.php.
Route::post('/payment/sepay/webhook', [Shop\PaymentController::class, 'sepayWebhook'])->name('payments.sepay.webhook');

// Chatbot AI tư vấn (khung chat nổi trên mọi trang). Giới hạn 20 tin/phút/IP.
Route::controller(Shop\ChatController::class)->prefix('chat')->name('chat.')->group(function () {
    Route::get('/config', 'config')->name('config');
    Route::post('/messages', 'send')->middleware('throttle:chat')->name('send');
    Route::post('/messages/stream', 'stream')->middleware('throttle:chat')->name('stream');
    Route::get('/sessions/{session}', 'session')->whereNumber('session')->name('session');
    Route::post('/messages/{message}/feedback', 'feedback')->whereNumber('message')->name('feedback');
});

Route::middleware('auth')->group(function () {
    Route::get('/checkout', [Shop\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [Shop\CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/order-success/{order}', [Shop\OrderController::class, 'success'])->name('orders.success');
    Route::get('/orders/{order}/payment-status', [Shop\PaymentController::class, 'status'])->name('orders.payment-status');
    Route::post('/orders/{order}/cancel', [Shop\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/reorder', [Shop\OrderController::class, 'reorder'])->name('orders.reorder');
    Route::post('/orders/{order}/pay', [Shop\PaymentController::class, 'retry'])->name('orders.pay');

    Route::post('/reviews', [Shop\ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/account', [Shop\AccountController::class, 'index'])->name('account.index');
    Route::put('/account/profile', [Shop\AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/address', [Shop\AccountController::class, 'updateAddress'])->name('account.address');
    Route::put('/account/password', [Shop\AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Xác thực: đăng nhập, đăng ký, quên mật khẩu bằng OTP
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.attempt');

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'requestOtp'])->middleware('throttle:otp-request')->name('password.otp');
    Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:otp-verify')->name('password.update');

    // Đăng nhập/đăng ký bằng Google hoặc Facebook (Firebase Authentication gửi ID token để máy chủ kiểm tra).
    Route::post('/auth/social', SocialLoginController::class)->middleware('throttle:login')->name('auth.social');
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
        Route::put('/{order}/customer', 'updateCustomer')->name('customer');
        Route::put('/{order}/payment', 'reconcilePayment')->name('payment');
        Route::delete('/{order}', 'destroy')->name('destroy');
    });

    Route::get('/customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::put('/customers/{user}', [Admin\CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{user}', [Admin\CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::patch('/customers/{user}/lock', [Admin\CustomerController::class, 'toggleLock'])->name('customers.lock');
    Route::post('/customers/{user}/vouchers', [Admin\CustomerController::class, 'giftVoucher'])->name('customers.vouchers.store');

    Route::get('/chatbot', [Admin\ChatbotController::class, 'index'])->name('chatbot.index');
    Route::put('/chatbot/config', [Admin\ChatbotController::class, 'updateConfig'])->name('chatbot.config');
    Route::resource('knowledge', Admin\KnowledgeController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/contacts', [Admin\ContactController::class, 'index'])->name('contacts.index');
    Route::put('/contacts/{contact}', [Admin\ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [Admin\ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/{status}', [Admin\ReviewController::class, 'moderate'])
        ->whereIn('status', ['approved', 'rejected'])->name('reviews.moderate');
    Route::put('/reviews/{review}', [Admin\ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
});
