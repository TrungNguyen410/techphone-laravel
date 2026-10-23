<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('logo')->default('');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Điện thoại: có thêm các cột thông số cấu hình (RAM, bộ nhớ, màn hình...).
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->foreignId('brand_id')->constrained('brands');
            $table->foreignId('category_id')->constrained('categories');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('old_price')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->string('image')->default('');
            $table->json('images')->nullable();
            $table->string('ram', 30)->default('');
            $table->string('storage', 30)->default('');
            $table->string('screen')->default('');
            $table->string('battery', 50)->default('');
            $table->string('camera')->default('');
            $table->string('chip')->default('');
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('sold')->default(0);
            $table->decimal('rating', 2, 1)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accessories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->foreignId('brand_id')->constrained('brands');
            $table->foreignId('category_id')->constrained('categories');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('old_price')->default(0);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->string('image')->default('');
            $table->json('images')->nullable();
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('sold')->default(0);
            $table->decimal('rating', 2, 1)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Danh sách yêu thích của tài khoản (điện thoại hoặc phụ kiện).
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['product', 'accessory']);
            $table->unsignedBigInteger('item_id');
            $table->timestamps();
            $table->unique(['user_id', 'item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('accessories');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
