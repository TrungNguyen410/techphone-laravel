<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Luồng mua hàng qua giao diện web: giỏ hàng → voucher → thanh toán → đơn hàng.
 */
class ShoppingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_add_respects_stock_and_checkout_creates_order(): void
    {
        $user = User::factory()->create();
        $phone = $this->makeProduct(['price' => 4000000, 'stock' => 3]);
        $this->makeVoucher(['code' => 'GIAM200K', 'type' => 'fixed', 'value' => 200000, 'max_discount' => 200000]);

        $this->post("/cart/add/product/{$phone->id}", ['quantity' => 10])->assertRedirect();
        $this->get('/cart')->assertOk()->assertSee($phone->name)->assertSee('Còn 3 sản phẩm');
        $this->post('/cart/voucher', ['code' => 'giam200k'])->assertSessionHas('toast.type', 'success');

        $this->actingAs($user)->get('/checkout')->assertOk()->assertSee('Hoàn tất đơn hàng');
        $response = $this->actingAs($user)->post('/checkout', $this->customer() + [
            'payment_method' => 'cod',
            'checkout_key' => 'checkout-test-1',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.success', $order));
        $this->assertSame(3, $order->items->first()->quantity, 'Số lượng bị giới hạn theo tồn kho');
        $this->assertSame(12000000 - 200000, $order->total, 'Miễn phí ship, trừ 200k');
        $this->assertSame(0, $phone->fresh()->stock);
        $this->get('/cart')->assertSee('Giỏ hàng đang trống');
    }

    public function test_checkout_requires_login_and_valid_data(): void
    {
        $phone = $this->makeProduct();
        $this->post("/cart/add/product/{$phone->id}");

        $this->get('/checkout')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->post('/checkout', $this->customer(['phone' => '123', 'province' => 'Sao Hỏa']) + ['payment_method' => 'cod', 'checkout_key' => 'k'])
            ->assertSessionHasErrors(['phone', 'province']);
        $this->assertSame(0, Order::count());
    }

    public function test_vnpay_is_hidden_when_not_configured(): void
    {
        config(['services.vnpay.tmn_code' => '', 'services.vnpay.hash_secret' => '']);
        $phone = $this->makeProduct();
        $user = User::factory()->create();
        $this->actingAs($user)->post("/cart/add/product/{$phone->id}");

        $this->get('/checkout')->assertDontSee('Thẻ/QR qua VNPay');
        $this->post('/checkout', $this->customer() + ['payment_method' => 'card', 'checkout_key' => 'k'])
            ->assertSessionHas('toast', fn ($toast) => $toast['message'] === 'Phương thức thanh toán chưa được hỗ trợ');
    }

    public function test_customer_can_only_view_own_order_success_page(): void
    {
        $owner = User::factory()->create();
        $order = Order::create([
            'order_number' => 'TP000001', 'user_id' => $owner->id, 'subtotal' => 1, 'total' => 1,
            'customer_name' => 'A', 'customer_email' => 'a@a.vn', 'customer_phone' => '0912345678', 'customer_address' => 'x',
        ]);

        $this->actingAs($owner)->get("/order-success/{$order->id}")->assertOk()->assertSee('TP000001');
        $this->actingAs(User::factory()->create())->get("/order-success/{$order->id}")->assertForbidden();
    }

    public function test_public_order_lookup_masks_customer_data(): void
    {
        Order::create([
            'order_number' => 'TP260601', 'subtotal' => 1, 'total' => 1, 'customer_name' => 'Nguyễn Minh Anh',
            'customer_email' => 'a@a.vn', 'customer_phone' => '0911111111', 'customer_address' => '45 Lê Lợi',
        ]);

        $this->get('/order-lookup?order_number=TP260601&phone=0911111111')
            ->assertOk()->assertSee('N***')->assertSee('091****111')->assertDontSee('45 Lê Lợi');
        $this->get('/order-lookup?order_number=TP260601&phone=0922222222')->assertSee('Không tìm thấy đơn hàng phù hợp');
    }

    public function test_review_is_pending_until_approved_and_only_once_per_item(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $phone = $this->makeProduct();
        $payload = ['target_type' => 'product', 'target_id' => $phone->id, 'rating' => 5, 'comment' => 'Máy chạy mượt, pin trâu.'];

        $this->actingAs($user)->post('/reviews', $payload)->assertSessionHas('toast.type', 'success');
        $this->actingAs($user)->post('/reviews', $payload)->assertSessionHas('toast.type', 'error');
        $this->get('/'); // bỏ qua lần hiển thị lại nội dung form (old input) sau khi báo lỗi
        $this->get("/products/{$phone->id}")->assertDontSee('Máy chạy mượt');

        $review = Review::firstOrFail();
        $this->actingAs($admin)->patch("/admin/reviews/{$review->id}/approved");
        $this->get("/products/{$phone->id}")->assertSee('Máy chạy mượt');
    }

    public function test_wishlist_and_compare_toggle(): void
    {
        $phone = $this->makeProduct();

        $this->postJson("/wishlist/product/{$phone->id}")->assertJson(['active' => true, 'count' => 1]);
        $this->postJson("/wishlist/product/{$phone->id}")->assertJson(['active' => false, 'count' => 0]);

        foreach (range(1, 4) as $i) {
            $this->postJson('/compare/'.$this->makeProduct()->id)->assertJson(['active' => true]);
        }
        $this->postJson("/compare/{$phone->id}")->assertJson(['active' => false, 'count' => 4]);
        $this->get('/compare')->assertOk()->assertSee('So sánh điện thoại');
    }

    public function test_catalog_filters(): void
    {
        $cheap = $this->makeProduct(['name' => 'Máy Rẻ', 'price' => 5000000]);
        $pricey = $this->makeProduct(['name' => 'Máy Đắt', 'price' => 35000000]);
        $hidden = $this->makeProduct(['name' => 'Máy Ẩn', 'status' => 'inactive']);

        $this->get('/products?price=30000000-999999999')->assertSee($pricey->name)->assertDontSee($cheap->name);
        $this->get('/products?q=rẻ')->assertSee($cheap->name)->assertDontSee($pricey->name);
        $this->get('/products')->assertDontSee($hidden->name);
        $this->get("/products/{$hidden->id}")->assertNotFound();
    }
}
