<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Thanh toán chuyển khoản tự động qua webhook SePay: đối chiếu mã đơn + số tiền, chống xử lý trùng,
 * cộng dồn khi chuyển thiếu, đơn đã hủy → cần hoàn tiền.
 */
class SepayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sepay-secret-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sepay.webhook_api_key' => self::KEY]);
    }

    private function bankOrder(array $attributes = []): Order
    {
        return Order::create($attributes + [
            'order_number' => 'TP2611180007', 'subtotal' => 1500000, 'total' => 1500000, 'payment_method' => 'bank',
            'customer_name' => 'A', 'customer_email' => 'a@a.vn', 'customer_phone' => '0912345678', 'customer_address' => 'x',
        ]);
    }

    private function webhook(array $payload, ?string $key = self::KEY)
    {
        return $this->postJson('/payment/sepay/webhook', $payload + [
            'id' => 1001, 'gateway' => 'Vietcombank', 'transferType' => 'in', 'transferAmount' => 1500000,
            'content' => 'MBVCB.123 TP2611180007 thanh toan',
        ], $key ? ['Authorization' => 'Apikey '.$key] : []);
    }

    public function test_requires_the_api_key(): void
    {
        $this->bankOrder();
        $this->webhook([], null)->assertUnauthorized();
        $this->webhook([], 'wrong-key')->assertUnauthorized();

        config(['services.sepay.webhook_api_key' => '']);
        $this->webhook([])->assertStatus(503);
        $this->assertSame('pending', Order::first()->payment_status);
    }

    public function test_full_transfer_confirms_the_order_once(): void
    {
        $order = $this->bankOrder();

        $this->webhook([])->assertOk()->assertJson(['success' => true, 'matched' => true, 'paymentStatus' => 'paid']);
        $this->webhook([])->assertOk()->assertJson(['duplicate' => true]);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('SEPAY-1001', $order->payment_reference);
        $this->assertStringContainsString('Tự động xác nhận qua SePay', $order->payment_note);
        $this->assertSame(1, PaymentTransaction::count());
    }

    public function test_partial_transfers_add_up(): void
    {
        $order = $this->bankOrder();

        $this->webhook(['id' => 1, 'transferAmount' => 1000000])->assertJson(['underpaid' => true, 'remaining' => 500000]);
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->webhook(['id' => 2, 'transferAmount' => 500000])->assertJson(['paymentStatus' => 'paid']);
        $this->assertStringContainsString('2 lần chuyển', $order->fresh()->payment_note);
    }

    public function test_cancelled_order_needs_refund_and_extra_money_is_noted(): void
    {
        $cancelled = $this->bankOrder(['status' => 'cancelled']);
        $this->webhook([])->assertJson(['paymentStatus' => 'refund_required']);
        $this->assertSame('cancelled', $cancelled->fresh()->status);

        $paid = $this->bankOrder(['order_number' => 'TP2611180008', 'payment_status' => 'paid']);
        $this->webhook(['id' => 2002, 'content' => 'TP2611180008'])->assertJson(['overpaid' => true]);
        $this->assertStringContainsString('cần hoàn cho khách', $paid->fresh()->payment_note);
    }

    public function test_ignores_outgoing_unknown_and_non_bank_transfers(): void
    {
        $this->bankOrder();
        $this->bankOrder(['order_number' => 'TP2611180009', 'payment_method' => 'cod']);

        $this->webhook(['transferType' => 'out'])->assertJson(['matched' => false, 'reason' => 'not_incoming']);
        $this->webhook(['content' => 'chuyen tien'])->assertJson(['matched' => false, 'reason' => 'order_number_missing']);
        $this->webhook(['content' => 'TP2611180009'])->assertJson(['matched' => false, 'reason' => 'order_not_found']);
        $this->webhook(['transferAmount' => 0])->assertStatus(400);
        $this->assertSame(0, PaymentTransaction::count());
    }

    public function test_success_page_shows_vietqr_by_order_number_and_polls_status(): void
    {
        $user = User::factory()->create();
        $order = $this->bankOrder(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('orders.success', $order))->assertOk()
            ->assertSee('addInfo=TP2611180007', false)
            ->assertSee('data-payment-poll', false)
            ->assertSee('tự động xác nhận');
        $this->actingAs($user)->getJson(route('orders.payment-status', $order))->assertOk()->assertJson(['payment_status' => 'pending']);
        $this->actingAs(User::factory()->create())->getJson(route('orders.payment-status', $order))->assertForbidden();

        $this->webhook([]);
        $this->actingAs($user)->get(route('orders.success', $order))->assertSee('Đã nhận thanh toán');
    }
}
