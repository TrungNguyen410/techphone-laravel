<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - shipping_address: địa chỉ nhận hàng đã lưu (họ tên, SĐT, email, số nhà, tỉnh, phường/xã) để lần sau
     *   khách không phải nhập lại khi đặt hàng.
     * - Tài khoản Google/Facebook (đăng nhập qua Firebase) không có số điện thoại và mật khẩu.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 15)->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->json('shipping_address')->nullable()->after('address');
            $table->enum('auth_provider', ['phone', 'google', 'facebook'])->default('phone')->after('password');
            $table->string('firebase_uid', 128)->nullable()->unique()->after('auth_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['firebase_uid']);
            $table->dropColumn(['shipping_address', 'auth_provider', 'firebase_uid']);
        });
    }
};
