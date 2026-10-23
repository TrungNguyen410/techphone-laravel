<?php

namespace Database\Seeders;

use App\Models\Accessory;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\SvgImageFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Danh mục, thương hiệu, 33 điện thoại và 13 phụ kiện mẫu (ảnh SVG được sinh tự động).
 */
class CatalogSeeder extends Seeder
{
    private const CATEGORIES = ['Điện thoại', 'Tai nghe', 'Sạc', 'Đồng hồ', 'Ốp lưng', 'Pin dự phòng', 'Cáp sạc', 'Loa', 'Bảo vệ màn hình', 'Giá đỡ'];

    private const BRANDS = ['Apple', 'Samsung', 'Xiaomi', 'OPPO', 'Vivo', 'Honor', 'Realme', 'Nothing', 'Google', 'OnePlus', 'Asus', 'Anker', 'Baseus', 'TechPhone', 'Sony', 'JBL'];

    /** [tên, hãng, giá, giá cũ, RAM, bộ nhớ, màu ảnh] */
    private const PHONES = [
        ['iPhone 16 Pro Max', 'Apple', 33990000, 36990000, '8GB', '256GB', '#2563eb'],
        ['iPhone 16 Pro', 'Apple', 28990000, 31990000, '8GB', '256GB', '#475569'],
        ['iPhone 16 Plus', 'Apple', 24990000, 27990000, '8GB', '128GB', '#ec4899'],
        ['iPhone 15 Pro Max', 'Apple', 26990000, 30990000, '8GB', '256GB', '#64748b'],
        ['iPhone 15', 'Apple', 18990000, 22990000, '6GB', '128GB', '#0ea5e9'],
        ['iPhone 14 Plus', 'Apple', 16990000, 19990000, '6GB', '128GB', '#a855f7'],
        ['Galaxy S25 Ultra', 'Samsung', 30990000, 33990000, '12GB', '256GB', '#1d4ed8'],
        ['Galaxy S25 Plus', 'Samsung', 23990000, 26990000, '12GB', '256GB', '#334155'],
        ['Galaxy S25', 'Samsung', 19990000, 22990000, '12GB', '256GB', '#38bdf8'],
        ['Galaxy Z Fold6', 'Samsung', 38990000, 43990000, '12GB', '512GB', '#64748b'],
        ['Galaxy Z Flip6', 'Samsung', 20990000, 25990000, '12GB', '256GB', '#f472b6'],
        ['Galaxy A56 5G', 'Samsung', 9990000, 11990000, '8GB', '256GB', '#22c55e'],
        ['Xiaomi 15 Ultra', 'Xiaomi', 29990000, 32990000, '16GB', '512GB', '#111827'],
        ['Xiaomi 15', 'Xiaomi', 21990000, 24990000, '12GB', '256GB', '#3b82f6'],
        ['Xiaomi 14T Pro', 'Xiaomi', 15990000, 17990000, '12GB', '512GB', '#f97316'],
        ['Redmi Note 14 Pro+', 'Xiaomi', 10990000, 12990000, '12GB', '256GB', '#8b5cf6'],
        ['Redmi Note 14', 'Xiaomi', 5990000, 6990000, '8GB', '128GB', '#0ea5e9'],
        ['OPPO Find X8 Pro', 'OPPO', 27990000, 30990000, '16GB', '512GB', '#0f766e'],
        ['OPPO Reno13 Pro', 'OPPO', 16990000, 18990000, '12GB', '512GB', '#7c3aed'],
        ['OPPO Reno13', 'OPPO', 13990000, 15990000, '12GB', '256GB', '#06b6d4'],
        ['OPPO A5 Pro', 'OPPO', 6990000, 7990000, '8GB', '256GB', '#14b8a6'],
        ['vivo X200 Pro', 'Vivo', 25990000, 29990000, '16GB', '512GB', '#2563eb'],
        ['vivo V50', 'Vivo', 12990000, 14990000, '12GB', '256GB', '#6366f1'],
        ['vivo Y39 5G', 'Vivo', 7490000, 8490000, '8GB', '256GB', '#0891b2'],
        ['HONOR Magic7 Pro', 'Honor', 24990000, 27990000, '12GB', '512GB', '#1e40af'],
        ['HONOR 400 Pro', 'Honor', 16990000, 18990000, '12GB', '512GB', '#9333ea'],
        ['realme GT 7 Pro', 'Realme', 18990000, 21990000, '16GB', '512GB', '#f59e0b'],
        ['realme 14 Pro+', 'Realme', 11990000, 13990000, '12GB', '256GB', '#06b6d4'],
        ['Nothing Phone (3a) Pro', 'Nothing', 13990000, 15990000, '12GB', '256GB', '#111827'],
        ['Google Pixel 9 Pro', 'Google', 23990000, 26990000, '16GB', '256GB', '#3b82f6'],
        ['OnePlus 13', 'OnePlus', 21990000, 24990000, '16GB', '512GB', '#ef4444'],
        ['ASUS ROG Phone 9', 'Asus', 24990000, 27990000, '16GB', '512GB', '#dc2626'],
    ];

    /** [tên, hãng, loại, giá, giá cũ, màu ảnh] */
    private const ACCESSORIES = [
        ['AirPods Pro 2 USB-C', 'Apple', 'Tai nghe', 5290000, 5990000, '#2563eb'],
        ['Galaxy Buds3 Pro', 'Samsung', 'Tai nghe', 3990000, 4690000, '#7c3aed'],
        ['Sạc nhanh GaN 65W', 'Anker', 'Sạc', 1090000, 1390000, '#0f766e'],
        ['Pin dự phòng 20.000mAh', 'Baseus', 'Pin dự phòng', 890000, 1190000, '#f97316'],
        ['Apple Watch Series 10', 'Apple', 'Đồng hồ', 9990000, 11990000, '#0ea5e9'],
        ['Galaxy Watch7', 'Samsung', 'Đồng hồ', 5990000, 6990000, '#1d4ed8'],
        ['Ốp lưng MagSafe trong suốt', 'TechPhone', 'Ốp lưng', 390000, 590000, '#14b8a6'],
        ['Cáp USB-C bện 100W', 'Anker', 'Cáp sạc', 350000, 490000, '#ef4444'],
        ['Tai nghe Sony WH-1000XM5', 'Sony', 'Tai nghe', 7490000, 8990000, '#111827'],
        ['Loa JBL Flip 6', 'JBL', 'Loa', 2590000, 3290000, '#06b6d4'],
        ['Kính cường lực Privacy', 'TechPhone', 'Bảo vệ màn hình', 250000, 350000, '#64748b'],
        ['Giá đỡ điện thoại ô tô', 'Baseus', 'Giá đỡ', 450000, 650000, '#f59e0b'],
    ];

    public function run(): void
    {
        $categories = collect(self::CATEGORIES)->mapWithKeys(fn ($name) => [
            $name => Category::create(['name' => $name, 'slug' => Str::slug($name), 'description' => '', 'active' => true]),
        ]);
        $brands = collect(self::BRANDS)->mapWithKeys(fn ($name) => [
            $name => Brand::create(['name' => $name, 'slug' => Str::slug($name), 'description' => "Sản phẩm {$name} chính hãng tại TechPhone.", 'active' => true]),
        ]);

        foreach (self::PHONES as $index => [$name, $brand, $price, $oldPrice, $ram, $storage, $color]) {
            $screen = number_format(6.1 + ($index % 7) * 0.1, 1).' inch OLED, 120Hz';
            $battery = (4400 + ($index % 8) * 150).' mAh';
            $camera = (48 + ($index % 4) * 16).'MP, chống rung quang học';
            $image = $this->storeSvg('products', $name, SvgImageFactory::product($name, $color));
            $back = $this->storeSvg('products', $name.'-back', SvgImageFactory::product($brand.' - Mặt sau', '#0f172a', '#e2e8f0'));

            Product::create([
                'name' => $name,
                'brand_id' => $brands[$brand]->id,
                'category_id' => $categories['Điện thoại']->id,
                'price' => $price,
                'old_price' => $oldPrice,
                'image' => $image,
                'images' => [$image, $back],
                'ram' => $ram,
                'storage' => $storage,
                'screen' => $screen,
                'battery' => $battery,
                'camera' => $camera,
                'chip' => $index % 3 === 0 ? 'Chip flagship thế hệ mới' : 'Chip 8 nhân hiệu năng cao',
                'description' => "{$name} mang đến trải nghiệm cao cấp với hiệu năng mạnh mẽ, camera sắc nét và thời lượng pin bền bỉ. Sản phẩm được kiểm định kỹ lưỡng, bảo hành rõ ràng tại TechPhone.",
                'specifications' => [
                    'Màn hình' => $screen,
                    'Hệ điều hành' => $brand === 'Apple' ? 'iOS' : 'Android',
                    'RAM' => $ram,
                    'Bộ nhớ trong' => $storage,
                    'Pin' => $battery,
                    'Camera' => (48 + ($index % 4) * 16).'MP',
                    'Kết nối' => '5G, Wi-Fi 6, Bluetooth 5.3',
                ],
                // iPhone 15 để tồn kho thấp nhằm minh họa cảnh báo sắp hết hàng.
                'stock' => $name === 'iPhone 15' ? 2 : 5 + (($index * 7) % 44),
                'sold' => 38 + (($index * 53) % 880),
                'rating' => round(4.2 + ($index % 8) / 10, 1),
                'status' => 'active',
                'created_at' => now()->subDays($index * 3),
            ]);
        }

        // Sản phẩm ngừng bán dùng để kiểm thử: không hiển thị ở cửa hàng, chỉ thấy trong trang quản trị.
        $hidden = $this->storeSvg('products', 'Demo Hidden Phone', SvgImageFactory::product('Hidden Phone', '#475569'));
        Product::create([
            'name' => 'Demo Hidden Phone', 'brand_id' => $brands['TechPhone']->id, 'category_id' => $categories['Điện thoại']->id,
            'price' => 9990000, 'old_price' => 0, 'image' => $hidden, 'images' => [$hidden], 'ram' => '8GB', 'storage' => '128GB',
            'description' => 'Sản phẩm ngừng bán dùng để kiểm tra trạng thái trong trang quản trị.', 'specifications' => [],
            'stock' => 0, 'sold' => 0, 'rating' => 0, 'status' => 'inactive',
        ]);

        foreach (self::ACCESSORIES as $index => [$name, $brand, $category, $price, $oldPrice, $color]) {
            $image = $this->storeSvg('accessories', $name, SvgImageFactory::accessory($name, $color));

            Accessory::create([
                'name' => $name,
                'brand_id' => $brands[$brand]->id,
                'category_id' => $categories[$category]->id,
                'price' => $price,
                'old_price' => $oldPrice,
                'image' => $image,
                'images' => [$image],
                'description' => "{$name} chính hãng, tương thích tốt, thiết kế bền đẹp và được bảo hành tại TechPhone.",
                'specifications' => ['Thương hiệu' => $brand, 'Loại phụ kiện' => $category, 'Bảo hành' => '12 tháng'],
                'stock' => 12 + $index * 5,
                'sold' => 40 + $index * 31,
                'rating' => round(4.3 + ($index % 6) / 10, 1),
                'status' => 'active',
                'created_at' => now()->subDays($index * 2),
            ]);
        }

        $hiddenAccessory = $this->storeSvg('accessories', 'Demo Hidden Accessory', SvgImageFactory::accessory('Hidden Accessory', '#475569'));
        Accessory::create([
            'name' => 'Demo Hidden Accessory', 'brand_id' => $brands['TechPhone']->id, 'category_id' => $categories['Ốp lưng']->id,
            'price' => 199000, 'old_price' => 0, 'image' => $hiddenAccessory, 'images' => [$hiddenAccessory],
            'description' => 'Phụ kiện ngừng bán dùng để kiểm tra trạng thái trong trang quản trị.', 'specifications' => [],
            'stock' => 0, 'sold' => 0, 'rating' => 0, 'status' => 'inactive',
        ]);
    }

    private function storeSvg(string $folder, string $name, string $svg): string
    {
        $path = "seed/{$folder}/".Str::slug($name).'.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
