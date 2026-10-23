<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_product_crud_with_images(): void
    {
        $brand = Brand::create(['name' => 'Samsung', 'slug' => 'samsung']);
        $category = Category::create(['name' => 'Điện thoại', 'slug' => 'dien-thoai']);

        $this->post('/admin/products', [
            'name' => 'Galaxy Test', 'brand_id' => $brand->id, 'category_id' => $category->id,
            'price' => 9000000, 'old_price' => 10000000, 'stock' => 7, 'status' => 'active',
            'description' => 'Mô tả', 'ram' => '8GB', 'storage' => '256GB',
            'new_images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();
        $this->assertSame(10, $product->discount_percent);
        $this->assertCount(2, $product->images);
        Storage::disk('public')->assertExists($product->image);
        $this->assertSame('8GB', $product->specifications['RAM']);

        $second = $product->images[1];
        $this->put("/admin/products/{$product->id}", [
            'name' => 'Galaxy Test 2', 'brand_id' => $brand->id, 'category_id' => $category->id,
            'price' => 9500000, 'stock' => 3, 'status' => 'inactive', 'description' => 'Mô tả mới',
            'keep_images' => [$second], 'primary_image' => $second,
        ])->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertSame('Galaxy Test 2', $product->name);
        $this->assertSame([$second], $product->images);
        $this->assertSame($second, $product->image);

        $this->delete("/admin/products/{$product->id}")->assertRedirect();
        $this->assertSoftDeleted($product);
    }

    public function test_product_validation_messages(): void
    {
        $this->post('/admin/products', ['name' => ''])
            ->assertSessionHasErrors(['name', 'brand_id', 'category_id', 'price', 'stock', 'description']);
    }

    public function test_brand_in_use_cannot_be_deleted(): void
    {
        $product = $this->makeProduct();

        $this->delete("/admin/brands/{$product->brand_id}")
            ->assertSessionHas('toast', fn ($toast) => str_contains($toast['message'], 'đang được sản phẩm sử dụng'));
        $this->assertNotSoftDeleted($product->brand);

        $this->post('/admin/brands', ['name' => 'Nokia', 'active' => '1'])->assertRedirect();
        $this->post('/admin/brands', ['name' => 'Nokia'])->assertSessionHasErrors('name');
        $this->assertSame('nokia', Brand::where('name', 'Nokia')->value('slug'));
    }

    public function test_voucher_crud_normalizes_code(): void
    {
        $this->post('/admin/vouchers', [
            'code' => 'sale50', 'type' => 'percent', 'value' => 50, 'min_order' => 0, 'max_discount' => 100000,
            'quantity' => 10, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => '1',
        ])->assertRedirect(route('admin.vouchers.index'));
        $this->assertTrue(Voucher::where('code', 'SALE50')->exists());

        $this->post('/admin/vouchers', [
            'code' => 'bad', 'type' => 'percent', 'value' => 150, 'min_order' => 0, 'max_discount' => 0,
            'quantity' => 0, 'start_date' => '2026-02-01', 'end_date' => '2026-01-01',
        ])->assertSessionHasErrors(['value', 'end_date']);
    }

    public function test_customer_lock_and_admin_cannot_be_locked(): void
    {
        $customer = User::factory()->create();

        $this->patch("/admin/customers/{$customer->id}/lock")->assertRedirect();
        $this->assertSame('locked', $customer->fresh()->status);
        $this->patch("/admin/customers/{$customer->id}/lock");
        $this->assertSame('active', $customer->fresh()->status);

        $this->patch("/admin/customers/{$this->admin->id}/lock")->assertNotFound();
    }

    public function test_viewing_new_contact_marks_it_read(): void
    {
        $contact = Contact::create(['full_name' => 'A', 'email' => 'a@a.vn', 'phone' => '0912345678', 'subject' => 'Hỏi', 'message' => 'Nội dung']);

        $this->get("/admin/contacts?view={$contact->id}")->assertOk()->assertSee('Nội dung');
        $this->assertSame('read', $contact->fresh()->status);

        $this->put("/admin/contacts/{$contact->id}", ['status' => 'resolved', 'admin_note' => 'Đã gọi'])->assertRedirect();
        $this->assertSame('resolved', $contact->fresh()->status);
    }

    public function test_settings_update_is_reflected_on_storefront(): void
    {
        $this->put('/admin/settings', [
            'storeName' => 'PhoneZone', 'hotline' => '1900 1234', 'email' => 'hi@phonezone.vn', 'address' => '1 Trần Phú',
        ])->assertSessionHas('toast.type', 'success');

        $this->get('/contact')->assertSee('Liên hệ PhoneZone')->assertSee('1900 1234');
    }
}
