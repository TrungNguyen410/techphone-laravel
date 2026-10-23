<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = app(OrderService::class);
    }

    public function test_creating_order_snapshots_items_decrements_stock_and_numbers_order(): void
    {
        $user = User::factory()->create();
        $phone = $this->makeProduct(['price' => 3000000, 'stock' => 5]);
        $accessory = $this->makeAccessory(['price' => 500000, 'stock' => 4]);

        $order = $this->orders->create([
            'items' => [
                ['type' => 'product', 'id' => $phone->id, 'quantity' => 2],
                ['type' => 'accessory', 'id' => $accessory->id, 'quantity' => 1],
            ],
            'customer' => $this->customer(['phone' => '+84912345678']),
            'payment_method' => 'cod',
        ], $user);

        $this->assertMatchesRegularExpression('/^TP\d{6}0001$/', $order->order_number);
        $this->assertSame(6500000, $order->subtotal);
        $this->assertSame(20000, $order->shipping_fee);
        $this->assertSame(6520000, $order->total);
        $this->assertSame('0912345678', $order->customer_phone, 'SĐT được chuẩn hóa');
        $this->assertCount(2, $order->items);
        $this->assertSame(3, $phone->fresh()->stock);
        $this->assertSame(2, $phone->fresh()->sold);
        $this->assertSame(3, $accessory->fresh()->stock);
    }

    public function test_server_ignores_client_prices_and_rejects_insufficient_stock(): void
    {
        $phone = $this->makeProduct(['stock' => 1]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('không đủ số lượng tồn kho');

        try {
            $this->orders->create([
                'items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 2, 'price' => 1]],
                'customer' => $this->customer(),
            ], null);
        } finally {
            $this->assertSame(1, $phone->fresh()->stock, 'Không trừ kho khi đơn thất bại');
            $this->assertSame(0, Order::count());
        }
    }

    public function test_inactive_product_cannot_be_ordered(): void
    {
        $phone = $this->makeProduct(['status' => 'inactive']);

        $this->expectExceptionMessage('Sản phẩm hiện không khả dụng');
        $this->orders->create(['items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]], 'customer' => $this->customer()], null);
    }

    public function test_voucher_is_applied_and_its_usage_reserved(): void
    {
        $voucher = $this->makeVoucher(['code' => 'TECH10', 'value' => 10, 'max_discount' => 1000000, 'min_order' => 5000000]);
        $phone = $this->makeProduct(['price' => 20000000]);

        $order = $this->orders->create([
            'items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]],
            'customer' => $this->customer(),
            'voucher_code' => 'tech10',
        ], null);

        $this->assertSame(1000000, $order->discount);
        $this->assertSame(19000000, $order->total, 'Miễn phí ship cho đơn từ 10 triệu');
        $this->assertSame('TECH10', $order->voucher_code);
        $this->assertSame(1, $voucher->fresh()->used);
    }

    public function test_used_up_and_expired_vouchers_are_rejected(): void
    {
        $phone = $this->makeProduct();
        $this->makeVoucher(['code' => 'USEDUP', 'quantity' => 2, 'used' => 2]);
        $this->makeVoucher(['code' => 'OLD', 'start_date' => '2020-01-01', 'end_date' => '2020-12-31']);
        $payload = fn ($code) => ['items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]], 'customer' => $this->customer(), 'voucher_code' => $code];

        foreach (['USEDUP' => 'hết lượt sử dụng', 'OLD' => 'hết hạn', 'NOPE' => 'không hợp lệ'] as $code => $message) {
            try {
                $this->orders->create($payload($code), null);
                $this->fail("Mã {$code} lẽ ra phải bị từ chối");
            } catch (BusinessException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
        $this->assertSame(10, $phone->fresh()->stock);
    }

    public function test_same_checkout_key_returns_existing_order(): void
    {
        $user = User::factory()->create();
        $phone = $this->makeProduct(['stock' => 10]);
        $data = ['items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]], 'customer' => $this->customer()];

        $first = $this->orders->create($data, $user, 'checkout-abc');
        $second = $this->orders->create($data, $user, 'checkout-abc');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(9, $phone->fresh()->stock);

        $this->expectExceptionMessage('nội dung đơn hàng khác');
        $this->orders->create(['items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 3]], 'customer' => $this->customer()], $user, 'checkout-abc');
    }

    public function test_cancelling_restores_stock_and_releases_voucher_once(): void
    {
        $user = User::factory()->create();
        $voucher = $this->makeVoucher(['code' => 'GIAM']);
        $phone = $this->makeProduct(['stock' => 5]);
        $order = $this->orders->create([
            'items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 2]],
            'customer' => $this->customer(),
            'voucher_code' => 'GIAM',
        ], $user);

        $this->orders->cancel($order, $user);
        $this->orders->cancel($order, $user);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $phone->fresh()->stock);
        $this->assertSame(0, $phone->fresh()->sold);
        $this->assertSame(0, $voucher->fresh()->used);
    }

    public function test_customer_cannot_cancel_someone_elses_or_shipped_order(): void
    {
        $owner = User::factory()->create();
        $phone = $this->makeProduct();
        $order = $this->orders->create(['items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]], 'customer' => $this->customer()], $owner);

        try {
            $this->orders->cancel($order, User::factory()->create());
            $this->fail('Khách khác không được hủy đơn');
        } catch (BusinessException $exception) {
            $this->assertSame(403, $exception->status());
        }

        $order->update(['status' => 'shipping']);
        $this->expectExceptionMessage('không còn có thể hủy');
        $this->orders->cancel($order, $owner);
    }

    public function test_status_transitions_follow_the_workflow(): void
    {
        $phone = $this->makeProduct();
        $order = $this->orders->create(['items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]], 'customer' => $this->customer()], null);

        $this->orders->updateStatus($order, 'confirmed');
        $this->orders->updateStatus($order, 'shipping');
        $this->orders->updateStatus($order, 'delivered');
        $this->orders->updateStatus($order, 'completed');
        $this->assertSame('completed', $order->fresh()->status);

        $this->expectExceptionMessage('Không thể đổi trạng thái');
        $this->orders->updateStatus($order, 'pending');
    }

    public function test_admin_cancel_via_status_restores_stock(): void
    {
        $phone = $this->makeProduct(['stock' => 3]);
        $order = $this->orders->create(['items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 3]], 'customer' => $this->customer()], null);
        $this->assertSame(0, $phone->fresh()->stock);

        $this->orders->updateStatus($order, 'cancelled');

        $this->assertSame(3, $phone->fresh()->stock);
    }

    public function test_lookup_requires_matching_phone(): void
    {
        $phone = $this->makeProduct();
        $order = $this->orders->create(['items' => [['type' => 'product', 'id' => $phone->id, 'quantity' => 1]], 'customer' => $this->customer()], null);

        $this->assertSame($order->id, $this->orders->lookup(strtolower($order->order_number), '0912 345 678')->id);

        $this->expectExceptionMessage('Không tìm thấy đơn hàng');
        $this->orders->lookup($order->order_number, '0987654321');
    }
}
