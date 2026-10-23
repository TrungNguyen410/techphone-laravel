<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->index();
            $table->enum('type', ['percent', 'fixed', 'shipping']);
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('min_order')->default(0);
            $table->unsignedBigInteger('max_discount')->default(0);
            $table->unsignedInteger('quantity')->default(0); // 0 = không giới hạn
            $table->unsignedInteger('used')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Bộ đếm sinh mã đơn hàng theo ngày: TP + yymmdd + số thứ tự 4 chữ số.
        Schema::create('order_counters', function (Blueprint $table) {
            $table->string('date_part', 6)->primary();
            $table->unsignedInteger('value')->default(0);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('request_fingerprint', 64)->default('');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'confirmed', 'shipping', 'delivered', 'completed', 'cancelled'])
                ->default('pending')->index();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            $table->enum('payment_method', ['cod', 'bank', 'momo', 'card'])->default('cod');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refund_required', 'refunded'])
                ->default('pending');
            $table->string('payment_reference', 150)->default('');
            $table->foreignId('payment_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->text('payment_note')->nullable();
            $table->string('shipping_provider', 100)->default('TechPhone Express');
            $table->string('tracking_number', 100)->default('');
            $table->timestamp('estimated_delivery')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 15)->index();
            $table->string('customer_address');
            $table->string('province', 100)->default('');
            $table->string('district', 100)->default('');
            $table->string('ward', 100)->default('');
            $table->text('note')->nullable();
            $table->string('voucher_code', 50)->nullable();
            $table->boolean('voucher_usage_released')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Ảnh chụp (snapshot) sản phẩm tại thời điểm đặt hàng.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['product', 'accessory'])->default('product');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accessory_id')->nullable()->constrained('accessories')->nullOnDelete();
            $table->string('name');
            $table->string('image')->default('');
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('total');
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('vnpay');
            $table->string('method', 20)->default('card');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('pending')->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('VND');
            $table->string('reference', 100)->unique();
            $table->string('provider_transaction_id', 100)->default('');
            $table->string('bank_code', 30)->default('');
            $table->string('response_code', 10)->default('');
            $table->text('payment_url')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_counters');
        Schema::dropIfExists('vouchers');
    }
};
