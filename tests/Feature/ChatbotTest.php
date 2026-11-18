<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\KnowledgeArticle;
use App\Models\Order;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Chatbot AI: chế độ offline (không có Gemini), Gemini function calling (giả lập HTTP), stream SSE,
 * quyền xem cuộc trò chuyện, đánh giá câu trả lời và trang quản trị.
 */
class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private const VISITOR = 'v-test-visitor-123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.api_key' => '']);
    }

    private function ask(string $message, array $extra = [])
    {
        return $this->postJson('/chat/messages', ['message' => $message, 'visitor_id' => self::VISITOR] + $extra);
    }

    public function test_offline_advice_respects_budget_brand_and_need(): void
    {
        $samsung = Brand::create(['name' => 'Samsung', 'slug' => 'samsung']);
        $cheap = $this->makeProduct(['name' => 'Galaxy A56', 'brand_id' => $samsung->id, 'price' => 9000000, 'battery' => '5000 mAh', 'sold' => 50]);
        $mid = $this->makeProduct(['name' => 'Galaxy S25', 'brand_id' => $samsung->id, 'price' => 18000000, 'battery' => '4000 mAh', 'sold' => 90]);
        $this->makeProduct(['name' => 'iPhone 16 Pro Max', 'price' => 32000000, 'sold' => 200]);

        $response = $this->ask('Tư vấn điện thoại Samsung dưới 20 triệu pin trâu')->assertCreated();

        $reply = $response->json('message.content');
        $this->assertStringContainsString('hãng Samsung', $reply);
        $this->assertStringContainsString('tối đa 20.000.000', $reply);
        $this->assertStringNotContainsString('iPhone', $reply);
        $cards = collect($response->json('message.products'));
        $this->assertSame([$cheap->id, $mid->id], $cards->pluck('id')->all(), 'Xếp theo pin (nhu cầu) chứ không theo bán chạy');

        // Câu sau không nhắc lại ngân sách: dùng tiêu chí đã nhớ trong cuộc trò chuyện.
        $follow = $this->ask('còn máy nào chụp ảnh đẹp không', ['session_id' => $response->json('sessionId')]);
        $this->assertStringContainsString('tối đa 20.000.000', $follow->json('message.content'));
        $this->assertEquals(['maxPrice' => 20000000, 'brand' => 'Samsung', 'need' => 'camera'],
            collect(ChatSession::first()->context)->only(['maxPrice', 'brand', 'need'])->all());
    }

    public function test_offline_out_of_budget_suggests_closest_prices(): void
    {
        $this->makeProduct(['name' => 'Máy rẻ', 'price' => 3000000]);
        $close = $this->makeProduct(['name' => 'Máy gần giá', 'price' => 27000000]);

        $reply = $this->ask('điện thoại trên 30 triệu')->json('message');

        $this->assertStringContainsString('Hiện cửa hàng chưa có', $reply['content']);
        $this->assertSame($close->id, $reply['products'][0]['id']);
    }

    public function test_offline_compare_accessories_policy_and_promotions(): void
    {
        $this->makeProduct(['name' => 'iPhone 16 Pro Max', 'chip' => 'A18 Pro', 'price' => 32000000]);
        $this->makeProduct(['name' => 'Galaxy S25 Ultra', 'chip' => 'Snapdragon 8 Elite', 'price' => 30000000]);
        $this->assertStringContainsString('So sánh nhanh', $this->ask('So sánh iPhone 16 Pro Max và Galaxy S25 Ultra')->json('message.content'));

        $this->makeAccessory(['name' => 'Sạc dự phòng Anker 20000mAh']);
        $this->assertStringContainsString('Sạc dự phòng Anker', $this->ask('có sạc dự phòng không')->json('message.content'));

        KnowledgeArticle::create(['title' => 'Chính sách bảo hành', 'category' => 'warranty', 'keywords' => ['bảo hành', 'bao hanh'], 'content' => 'Bảo hành 12 tháng chính hãng.', 'active' => true]);
        $this->assertStringContainsString('Bảo hành 12 tháng', $this->ask('Chính sách bảo hành thế nào?')->json('message.content'));

        $this->makeVoucher(['code' => 'TECH10']);
        $this->makeVoucher(['code' => 'TRIANRIENG', 'assigned_user_id' => User::factory()->create()->id]);
        $promotions = $this->ask('Có mã giảm giá nào không?')->json('message.content');
        $this->assertStringContainsString('TECH10', $promotions);
        $this->assertStringNotContainsString('TRIANRIENG', $promotions, 'Voucher tặng riêng không lộ qua chatbot');
        $this->assertStringContainsString('chưa áp dụng được', $this->ask('mã TRIANRIENG dùng được không')->json('message.content'));

        $this->assertStringContainsString('chỉ hỗ trợ', $this->ask('thời tiết hôm nay thế nào')->json('message.content'));
    }

    public function test_offline_order_lookup_needs_order_number_and_phone_and_is_rate_limited(): void
    {
        $order = Order::create([
            'order_number' => 'TP2611180001', 'subtotal' => 1000000, 'total' => 1000000, 'status' => 'shipping',
            'customer_name' => 'A', 'customer_email' => 'a@a.vn', 'customer_phone' => '0912345678', 'customer_address' => 'x',
        ]);

        $this->assertStringContainsString('số điện thoại người nhận', $this->ask('tra cứu đơn TP2611180001')->json('message.content'));
        $found = $this->ask('kiểm tra đơn TP2611180001 sđt 0912 345 678')->json('message.content');
        $this->assertStringContainsString('Đang giao hàng', $found);
        $this->assertStringContainsString($order->order_number, $found);

        foreach (range(1, 9) as $attempt) {
            $this->ask('đơn TP2611180001 số 0900000000');
        }
        $this->assertStringContainsString('quá nhiều lần', $this->ask('đơn TP2611180001 số 0912345678')->json('message.content'));
    }

    public function test_sessions_belong_to_their_owner_and_feedback_is_saved(): void
    {
        $first = $this->ask('xin chào')->assertCreated();
        $sessionId = $first->json('sessionId');
        $messageId = $first->json('message.id');

        $this->getJson("/chat/sessions/{$sessionId}?visitor_id=".self::VISITOR)->assertOk()->assertJsonCount(2, 'messages');
        $this->getJson("/chat/sessions/{$sessionId}?visitor_id=v-someone-else-99")->assertNotFound();
        $this->postJson('/chat/messages', ['message' => 'hi', 'visitor_id' => 'v-someone-else-99', 'session_id' => $sessionId])->assertNotFound();

        $this->postJson("/chat/messages/{$messageId}/feedback", ['value' => 'up', 'visitor_id' => self::VISITOR])->assertOk();
        $this->assertSame('up', ChatMessage::find($messageId)->feedback);
        $this->postJson("/chat/messages/{$messageId}/feedback", ['value' => 'up', 'visitor_id' => 'v-someone-else-99'])->assertNotFound();
    }

    public function test_validation_disabled_bot_and_rate_limit(): void
    {
        $this->postJson('/chat/messages', ['message' => '', 'visitor_id' => self::VISITOR])->assertUnprocessable();
        $this->postJson('/chat/messages', ['message' => str_repeat('a', 1001), 'visitor_id' => self::VISITOR])->assertUnprocessable();
        $this->postJson('/chat/messages', ['message' => 'hi'])->assertUnprocessable();

        app(SettingService::class)->save(['chatbotEnabled' => false], 'chatbot');
        $this->ask('xin chào')->assertStatus(503);
        $this->get('/')->assertDontSee('data-chat-widget', false);

        app(SettingService::class)->save(['chatbotEnabled' => true], 'chatbot');
        $this->get('/')->assertSee('data-chat-widget', false);
        foreach (range(1, 17) as $attempt) {
            $this->ask('xin chào');
        }
        $this->ask('xin chào')->assertStatus(429);
    }

    public function test_stream_endpoint_sends_session_deltas_and_done(): void
    {
        $response = $this->post('/chat/messages/stream', ['message' => 'xin chào', 'visitor_id' => self::VISITOR], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('"type":"session"', $body);
        $this->assertStringContainsString('"type":"delta"', $body);
        $this->assertStringContainsString('"type":"done"', $body);

        $this->post('/chat/messages/stream', ['message' => 'hi', 'visitor_id' => 'v-other-visitor-1', 'session_id' => ChatSession::first()->id], ['Accept' => 'text/event-stream'])
            ->assertNotFound()->assertJson(['success' => false]);
    }

    public function test_gemini_function_calling_uses_real_store_data(): void
    {
        config(['services.gemini.api_key' => 'test-key', 'services.gemini.fallback_model' => '']);
        $phone = $this->makeProduct(['name' => 'Galaxy S25', 'price' => 18000000]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [
                    ['functionCall' => ['name' => 'search_products', 'args' => ['maxPrice' => 20000000]]],
                ]]]]])
                ->push(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'Mình gợi ý **Galaxy S25** giá 18 triệu.']]]]]]),
        ]);

        $message = $this->ask('máy dưới 20 triệu')->assertCreated()->json('message');

        $this->assertSame('Mình gợi ý **Galaxy S25** giá 18 triệu.', $message['content']);
        $this->assertSame($phone->id, $message['products'][0]['id']);
        $stored = ChatMessage::find($message['id']);
        $this->assertSame('gemini', $stored->source);
        $this->assertSame('search_products', $stored->tool_calls[0]['name']);
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-key')
            && str_contains($request->url(), 'gemini-3.5-flash:generateContent')
            && isset($request['tools'][0]['functionDeclarations']));
        // Lượt 2 gửi kèm kết quả công cụ cho mô hình.
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), '"functionResponse"'));
    }

    public function test_gemini_quota_error_falls_back_to_offline_and_cools_down(): void
    {
        config(['services.gemini.api_key' => 'test-key', 'services.gemini.fallback_model' => '']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'quota']], 429)]);

        $message = $this->ask('xin chào')->assertCreated()->json('message');

        $this->assertStringContainsString('Xin chào', $message['content']);
        $this->assertSame('fallback', ChatMessage::find($message['id'])->source);
        $this->assertSame(['gemini-3.5-flash'], app(\App\Services\Chat\GeminiClient::class)->quotaExhausted());
        Http::assertSentCount(1);
    }

    public function test_admin_sees_stats_transcript_and_can_configure(): void
    {
        $this->ask('xin chào');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/chatbot')->assertOk()->assertSee('Chatbot đang chạy chế độ offline')->assertSee('xin chào');
        $this->actingAs($admin)->get('/admin/chatbot?view='.ChatSession::first()->id)->assertOk()->assertSee('Hội thoại chatbot');

        $this->actingAs($admin)->put('/admin/chatbot/config', [
            'enabled' => '1', 'greeting' => 'Chào bạn **thân mến**', 'questions' => "Câu 1\nCâu 2",
        ])->assertRedirect();
        $config = app(ChatService::class)->config();
        $this->assertSame('Chào bạn **thân mến**', $config['greeting']);
        $this->assertSame(['Câu 1', 'Câu 2'], $config['quickQuestions']);

        $this->actingAs($admin)->put('/admin/chatbot/config', ['enabled' => '1', 'questions' => implode("\n", range(1, 7))])
            ->assertSessionHasErrors('questions');

        $this->actingAs($admin)->post('/admin/knowledge', [
            'title' => 'Giờ mở cửa', 'category' => 'store', 'keywords_text' => 'giờ mở cửa, mo cua, mo cua', 'content' => 'Mở cửa 8:00 - 21:30 mỗi ngày.', 'active' => '1',
        ])->assertRedirect();
        $this->assertSame(['giờ mở cửa', 'mo cua'], KnowledgeArticle::first()->keywords);
        $this->actingAs($admin)->get('/admin/knowledge')->assertOk()->assertSee('Giờ mở cửa');
    }
}
