<?php

namespace Tests;

use App\Models\Accessory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Tạo nhanh một điện thoại đang bán. */
    protected function makeProduct(array $attributes = []): Product
    {
        return Product::create($attributes + [
            'name' => 'Điện thoại thử '.uniqid(),
            'brand_id' => Brand::firstOrCreate(['slug' => 'apple'], ['name' => 'Apple'])->id,
            'category_id' => Category::firstOrCreate(['slug' => 'dien-thoai'], ['name' => 'Điện thoại'])->id,
            'price' => 5000000,
            'old_price' => 6000000,
            'ram' => '8GB',
            'storage' => '256GB',
            'battery' => '5000 mAh',
            'stock' => 10,
            'sold' => 0,
            'status' => 'active',
        ]);
    }

    protected function makeAccessory(array $attributes = []): Accessory
    {
        return Accessory::create($attributes + [
            'name' => 'Phụ kiện thử '.uniqid(),
            'brand_id' => Brand::firstOrCreate(['slug' => 'anker'], ['name' => 'Anker'])->id,
            'category_id' => Category::firstOrCreate(['slug' => 'sac'], ['name' => 'Sạc'])->id,
            'price' => 500000,
            'old_price' => 500000,
            'stock' => 20,
            'status' => 'active',
        ]);
    }

    protected function makeVoucher(array $attributes = []): Voucher
    {
        return Voucher::create($attributes + [
            'code' => 'TEST10',
            'type' => 'percent',
            'value' => 10,
            'min_order' => 0,
            'max_discount' => 1000000,
            'quantity' => 100,
            'used' => 0,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'active' => true,
        ]);
    }

    /** Dữ liệu người nhận hợp lệ cho form thanh toán. */
    protected function customer(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Nguyễn Văn A',
            'email' => 'a@example.com',
            'phone' => '0912345678',
            'address' => '1 Lê Lợi',
            'province' => 'TP. Hồ Chí Minh',
            'district' => '',
            'ward' => 'Bến Nghé',
        ];
    }
}
