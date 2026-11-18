<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\DashboardService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Địa chỉ nhận hàng đã lưu, quy tắc tự hủy đơn, voucher tặng riêng, dashboard doanh thu năm/tháng/ngày,
 * top khách hàng, tồn kho thấp và ảnh quản trị (tải từ máy tính hoặc URL).
 */
class CustomerFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(User $user, array $data): \Illuminate\Testing\TestResponse
    {
        $phone = $this->makeProduct(['price' => 4000000]);
        $this->actingAs($user)->post("/cart/add/product/{$phone->id}");

        return $this->actingAs($user)->post('/checkout', $data + ['payment_method' => 'cod', 'checkout_key' => 'k-'.uniqid()]);
    }

    public function test_first_order_saves_the_address_and_next_checkout_skips_the_form(): void
    {
        $user = User::factory()->create();
        $this->checkout($user, $this->customer() + ['address_mode' => 'new'])->assertRedirect();

        $saved = $user->fresh()->savedShippingAddress();
        $this->assertSame('Phường Sài Gòn', $saved['ward']);
        $this->assertSame('1 Lê Lợi', $saved['address']);

        $phone = $this->makeProduct();
        $this->actingAs($user)->post("/cart/add/product/{$phone->id}");
        $this->actingAs($user)->get('/checkout')->assertOk()
            ->assertSee('Giao đến địa chỉ đã lưu')->assertSee('1 Lê Lợi, Phường Sài Gòn, TP. Hồ Chí Minh')
            ->assertDontSee('name="full_name" autocomplete', false);
        $this->actingAs($user)->get('/checkout?address=new')->assertOk()->assertSee('Lưu làm địa chỉ mặc định');

        // Dùng địa chỉ đã lưu: máy chủ lấy từ tài khoản, bỏ qua giá trị bị sửa trên trình duyệt.
        $this->actingAs($user)->post('/checkout', ['address_mode' => 'saved', 'address' => 'Địa chỉ bị sửa', 'payment_method' => 'cod', 'checkout_key' => 'k-saved'])->assertRedirect();
        $this->assertSame('1 Lê Lợi', Order::latest('id')->first()->customer_address);
    }

    public function test_new_address_replaces_the_saved_one_only_when_ticked(): void
    {
        $user = User::factory()->create(['shipping_address' => $this->customer()]);

        $this->checkout($user, $this->customer(['address' => '99 Hai Bà Trưng', 'province' => 'Hà Nội', 'ward' => 'cau giay']) + ['address_mode' => 'new'])->assertRedirect();
        $this->assertSame('1 Lê Lợi', $user->fresh()->shipping_address['address'], 'Không tick thì giữ địa chỉ cũ');
        $this->assertSame('Phường Cầu Giấy', Order::latest('id')->first()->ward, 'Tên phường gõ tắt được đổi về tên chính thức');

        $this->checkout($user, $this->customer(['address' => '99 Hai Bà Trưng', 'province' => 'Hà Nội', 'ward' => 'Phường Cầu Giấy']) + ['address_mode' => 'new', 'save_address' => '1'])->assertRedirect();
        $this->assertSame('99 Hai Bà Trưng', $user->fresh()->shipping_address['address']);
    }

    public function test_ward_must_belong_to_the_province_and_account_can_edit_the_address(): void
    {
        $user = User::factory()->create();
        $this->checkout($user, $this->customer(['ward' => 'Phường Cầu Giấy']) + ['address_mode' => 'new'])->assertSessionHasErrors('ward');

        $this->actingAs($user)->get('/account?tab=address')->assertOk()->assertSee('Địa chỉ nhận hàng');
        $this->actingAs($user)->put('/account/address', $this->customer(['ward' => 'ben thanh']))->assertRedirect();
        $this->assertSame('Phường Bến Thành', $user->fresh()->shipping_address['ward']);
    }

    public function test_customer_can_only_cancel_pending_orders_but_admin_can_cancel_confirmed(): void
    {
        $user = User::factory()->create();
        $make = fn (string $status) => Order::create([
            'order_number' => 'TP'.random_int(100000, 999999), 'user_id' => $user->id, 'status' => $status, 'subtotal' => 1, 'total' => 1,
            'customer_name' => 'A', 'customer_email' => 'a@a.vn', 'customer_phone' => '0912345678', 'customer_address' => 'x',
        ]);

        $confirmed = $make('confirmed');
        $this->actingAs($user)->post("/orders/{$confirmed->id}/cancel")->assertSessionHas('toast.type', 'error');
        $this->assertSame('confirmed', $confirmed->fresh()->status);
        $this->actingAs($user)->get("/account?tab=orders&order={$confirmed->id}")->assertSee('không thể tự hủy');

        $pending = $make('pending');
        $this->actingAs($user)->post("/orders/{$pending->id}/cancel")->assertSessionHas('toast.type', 'success');
        $this->assertSame('cancelled', $pending->fresh()->status);

        app(OrderService::class)->cancel($confirmed, User::factory()->create(['role' => 'admin']));
        $this->assertSame('cancelled', $confirmed->fresh()->status);
    }

    public function test_admin_gifts_a_private_voucher_only_that_customer_can_use(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['full_name' => 'Khách Thân Thiết']);
        $other = User::factory()->create();

        $this->actingAs($admin)->get("/admin/dashboard?gift={$customer->id}")->assertOk()->assertSee('Tặng voucher')->assertSee('Khách Thân Thiết');
        $this->actingAs($admin)->post("/admin/customers/{$customer->id}/vouchers", [
            'type' => 'percent', 'value' => 150, 'end_date' => now()->addWeek()->toDateString(),
        ])->assertSessionHasErrors('value');
        $this->actingAs($admin)->post("/admin/customers/{$customer->id}/vouchers", [
            'type' => 'percent', 'value' => 15, 'max_discount' => 300000, 'end_date' => now()->addWeek()->toDateString(),
            'return_to' => url('/admin/dashboard?year=2026&gift='.$customer->id),
        ])->assertRedirect(url('/admin/dashboard?year=2026'))->assertSessionHas('gifted_voucher');

        $voucher = Voucher::where('assigned_user_id', $customer->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^TRIAN[A-Z2-9]{6}$/', $voucher->code);
        $this->assertSame(1, $voucher->quantity);
        $this->assertSame('Tri ân khách hàng Khách Thân Thiết', $voucher->note);

        $this->actingAs($customer)->get('/account?tab=vouchers')->assertOk()->assertSee($voucher->code)->assertSee('Dùng được');
        $this->actingAs($other)->get('/account?tab=vouchers')->assertDontSee($voucher->code);

        $phone = $this->makeProduct(['price' => 2000000]);
        $this->actingAs($other)->post("/cart/add/product/{$phone->id}");
        $this->actingAs($other)->post('/cart/voucher', ['code' => $voucher->code])->assertSessionHas('toast', fn ($toast) => $toast['message'] === 'Mã giảm giá không hợp lệ');

        $this->actingAs($customer)->post("/cart/add/product/{$phone->id}");
        $this->actingAs($customer)->post('/cart/voucher', ['code' => $voucher->code])->assertSessionHas('toast.type', 'success');
        $this->actingAs($customer)->post('/checkout', $this->customer() + ['payment_method' => 'cod', 'checkout_key' => 'gift-1'])->assertRedirect();
        $this->assertSame(300000, Order::latest('id')->first()->discount);
        $this->assertSame('used', $voucher->fresh()->ownerStatus());
    }

    public function test_dashboard_revenue_series_top_customers_and_low_stock(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $buyers = User::factory()->count(6)->create();
        $order = fn (User $user, int $total, string $date, string $status = 'completed') => (new Order)->forceFill([
            'order_number' => 'TP'.random_int(1000000, 9999999), 'user_id' => $user->id, 'status' => $status, 'subtotal' => $total,
            'total' => $total, 'customer_name' => 'A', 'customer_email' => 'a@a.vn', 'customer_phone' => '0912345678',
            'customer_address' => 'x', 'created_at' => $date, 'updated_at' => $date,
        ])->save();
        foreach ($buyers as $index => $buyer) {
            $order($buyer, ($index + 1) * 1000000, '2026-11-0'.($index + 1).' 09:00:00');
        }
        $order($buyers[0], 9000000, '2026-11-10 09:00:00', 'pending');
        $order($buyers[0], 2000000, '2024-05-10 09:00:00');

        $dashboard = app(DashboardService::class);
        $revenue = $dashboard->revenue(2026, 11);
        $this->assertCount(18, $revenue['daily'], 'Tháng hiện tại chỉ vẽ tới hôm nay');
        $this->assertSame(1000000, $revenue['daily'][0]);
        $this->assertSame(21000000, $revenue['monthly'][10]);
        $this->assertCount(11, $revenue['monthly'], 'Năm hiện tại chỉ vẽ tới tháng này');
        $this->assertCount(12, $dashboard->revenue(2025, 6)['monthly']);
        $this->assertSame([2022, 2023, 2024, 2025, 2026], array_column($revenue['yearly'], 'year'));
        $this->assertSame(2000000, $revenue['yearly'][2]['total']);
        $this->assertSame([], $dashboard->revenue(2026, 12)['daily'], 'Tháng chưa tới để trống');
        $this->assertCount(31, $dashboard->revenue(2026, 10)['daily']);

        $top = $dashboard->topCustomers();
        $this->assertCount(5, $top);
        $this->assertSame($buyers[5]->id, $top->first()->id);

        $this->makeProduct(['name' => 'Sắp hết A', 'stock' => 3]);
        $this->makeProduct(['name' => 'Hết B', 'stock' => 0]);
        $this->makeAccessory(['name' => 'Phụ kiện sắp hết', 'stock' => 10]);
        $this->makeProduct(['name' => 'Còn nhiều', 'stock' => 50]);
        $low = $dashboard->lowStock();
        $this->assertSame(3, $low['total']);
        $this->assertSame(1, $low['outOfStock']);
        $this->assertSame('Hết B', $low['items']->first()->name);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/dashboard')->assertOk()
            ->assertSee('Theo năm')->assertSee('Theo tháng')->assertSee('Theo ngày')
            ->assertSee('Từ 1/11 đến hôm nay 18/11/2026')->assertSee('Top 5 khách hàng')->assertSee('Cảnh báo tồn kho')
            ->assertSee('data-revenue-chart="daily"', false);
        Carbon::setTestNow();
    }

    public function test_admin_images_can_come_from_the_computer_or_a_url(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/banners', ['title' => 'A', 'description' => 'B', 'link' => '/products'])
            ->assertSessionHasErrors('image_file');
        $this->actingAs($admin)->post('/admin/banners', [
            'title' => 'Banner URL', 'description' => 'B', 'link' => '/products', 'image_url' => 'https://cdn.example.com/banner.jpg',
        ])->assertRedirect();
        $this->assertSame('https://cdn.example.com/banner.jpg', Banner::firstWhere('title', 'Banner URL')->image);
        $this->actingAs($admin)->post('/admin/banners', [
            'title' => 'Banner máy', 'description' => 'B', 'link' => '/products', 'image_file' => UploadedFile::fake()->image('banner.png'),
        ])->assertRedirect();
        Storage::disk('public')->assertExists(Banner::firstWhere('title', 'Banner máy')->image);

        $brand = $this->makeProduct()->brand_id;
        $category = Product::first()->category_id;
        $this->actingAs($admin)->post('/admin/products', [
            'name' => 'Máy ảnh mới', 'brand_id' => $brand, 'category_id' => $category, 'price' => 1000000, 'stock' => 5,
            'status' => 'active', 'description' => 'Mô tả',
            'image_urls' => ['https://cdn.example.com/a.jpg'],
            'new_images' => [UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.webp')],
            'primary_image' => 'https://cdn.example.com/a.jpg',
        ])->assertRedirect();
        $product = Product::firstWhere('name', 'Máy ảnh mới');
        $this->assertSame('https://cdn.example.com/a.jpg', $product->image);
        $this->assertCount(3, $product->galleryImages());

        $this->actingAs($admin)->get('/admin/products?edit='.$product->id)->assertOk()
            ->assertSee('Tải ảnh lên từ máy tính')->assertSee('Hoặc thêm ảnh bằng URL');
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Tải ảnh lên từ máy tính')->assertSee('Hoặc dùng URL ảnh');
    }
}
