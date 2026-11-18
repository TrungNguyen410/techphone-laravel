<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chatbot AI: cuộc trò chuyện, tin nhắn (kèm thẻ sản phẩm, công cụ đã gọi, đánh giá 👍/👎)
     * và kho kiến thức (chính sách cửa hàng) do quản trị viên cập nhật.
     */
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Khách chưa đăng nhập được nhận diện bằng mã ngẫu nhiên lưu trong trình duyệt.
            $table->string('visitor_id', 100)->default('')->index();
            $table->string('title')->default('Cuộc trò chuyện mới');
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable()->index();
            // Tiêu chí tư vấn (khoảng giá, hãng, nhu cầu) ghi nhớ giữa các câu hỏi ở chế độ offline.
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->json('products')->nullable();
            $table->json('tool_calls')->nullable();
            $table->enum('source', ['user', 'gemini', 'offline', 'fallback'])->default('user');
            $table->enum('feedback', ['up', 'down'])->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamps();
            $table->index(['chat_session_id', 'id']);
            $table->index(['role', 'source']);
        });

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->enum('category', ['warranty', 'return', 'shipping', 'payment', 'installment', 'store', 'faq'])
                ->default('faq')->index();
            $table->text('content');
            $table->json('keywords')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
