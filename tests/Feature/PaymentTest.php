<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'TESTSECRET';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.vnpay.tmn_code' => 'TESTCODE', 'services.vnpay.hash_secret' => self::SECRET]);
    }

    private function cardOrder(): Order
    {
        $phone = $this->makeProduct(['price' => 2000000]);

        return app(OrderService::class)->create([
            'items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]],
            'customer' => $this->customer(),
            'payment_method' => 'card',
        ], User::factory()->create());
    }

    /** Giả lập dữ liệu VNPay gửi về, ký bằng cùng thuật toán HMAC-SHA512. */
    private function signedCallback(string $reference, int $amount, string $code = '00'): array
    {
        $params = [
            'vnp_Amount' => $amount * 100,
            'vnp_BankCode' => 'NCB',
            'vnp_ResponseCode' => $code,
            'vnp_TmnCode' => 'TESTCODE',
            'vnp_TransactionNo' => '14000000',
            'vnp_TransactionStatus' => $code,
            'vnp_TxnRef' => $reference,
        ];
        ksort($params);

        return $params + ['vnp_SecureHash' => hash_hmac('sha512', http_build_query($params), self::SECRET)];
    }

    public function test_payment_url_is_signed_and_reused_within_ttl(): void
    {
        $order = $this->cardOrder();
        $payments = app(PaymentService::class);

        $url = $payments->startVnpayCheckout($order, '127.0.0.1');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame((string) ($order->total * 100), $query['vnp_Amount']);
        $this->assertSame($url, $payments->startVnpayCheckout($order, '127.0.0.1'));
        $this->assertSame(1, $order->transactions()->count());
    }

    public function test_valid_ipn_marks_order_paid_and_confirmed(): void
    {
        $order = $this->cardOrder();
        app(PaymentService::class)->startVnpayCheckout($order, null);
        $reference = $order->fresh()->payment_reference;

        $this->getJson('/payment/vnpay/ipn?'.http_build_query($this->signedCallback($reference, $order->total)))
            ->assertJson(['RspCode' => '00']);
        $this->getJson('/payment/vnpay/ipn?'.http_build_query($this->signedCallback($reference, $order->total)))
            ->assertJson(['RspCode' => '02']);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_tampered_signature_or_amount_is_rejected(): void
    {
        $order = $this->cardOrder();
        app(PaymentService::class)->startVnpayCheckout($order, null);
        $reference = $order->fresh()->payment_reference;

        $tampered = $this->signedCallback($reference, $order->total);
        $tampered['vnp_Amount'] = 100;
        $this->getJson('/payment/vnpay/ipn?'.http_build_query($tampered))->assertJson(['RspCode' => '97']);

        $wrongAmount = $this->signedCallback($reference, 1000);
        $this->getJson('/payment/vnpay/ipn?'.http_build_query($wrongAmount))->assertJson(['RspCode' => '04']);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_failed_payment_marks_order_failed(): void
    {
        $order = $this->cardOrder();
        app(PaymentService::class)->startVnpayCheckout($order, null);

        $this->get('/payment/vnpay/return?'.http_build_query($this->signedCallback($order->fresh()->payment_reference, $order->total, '24')))
            ->assertOk()->assertSee('Giao dịch chưa hoàn tất');

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_admin_reconciles_bank_transfer(): void
    {
        $admin = User::factory()->admin()->create();
        $phone = $this->makeProduct();
        $order = app(OrderService::class)->create([
            'items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]],
            'customer' => $this->customer(),
            'payment_method' => 'bank',
        ], null);

        $this->actingAs($admin)->put("/admin/orders/{$order->id}/payment", ['payment_status' => 'paid'])
            ->assertSessionHasErrors('reference');
        $this->actingAs($admin)->put("/admin/orders/{$order->id}/payment", ['payment_status' => 'paid', 'reference' => 'FT123', 'note' => 'Đã nhận'])
            ->assertSessionHas('toast.type', 'success');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame($admin->id, $order->payment_confirmed_by);
    }
}
