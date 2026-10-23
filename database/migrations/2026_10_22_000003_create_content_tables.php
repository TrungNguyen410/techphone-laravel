<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // product_id và accessory_id cùng NULL nghĩa là đánh giá chung về dịch vụ cửa hàng.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accessory_id')->nullable()->constrained('accessories')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->json('images')->nullable();
            $table->boolean('verified_purchase')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->default('');
            $table->string('image');
            $table->string('link')->default('/');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 15);
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['new', 'read', 'resolved'])->default('new')->index();
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->string('group', 50)->default('general')->index();
            $table->string('label')->default('');
            $table->timestamps();
        });

        // Mã OTP gửi qua SMS cho đăng ký và quên mật khẩu (lưu dạng băm).
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('target', 30);
            $table->enum('channel', ['sms'])->default('sms');
            $table->enum('purpose', ['register', 'password-reset']);
            $table->string('code_hash', 64);
            $table->text('payload')->nullable(); // JSON đã mã hóa (encrypted:array)
            $table->unsignedTinyInteger('attempts')->default(0);
            // DATETIME thay vì TIMESTAMP: MariaDB (XAMPP) tự gắn ON UPDATE CURRENT_TIMESTAMP cho cột
            // TIMESTAMP NOT NULL đầu tiên, khiến OTP "hết hạn" ngay khi tăng số lần nhập sai.
            $table->dateTime('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['target', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('reviews');
    }
};
