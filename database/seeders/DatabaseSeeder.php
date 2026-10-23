<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed dữ liệu mẫu cho toàn bộ cửa hàng: php artisan migrate:fresh --seed
     * (Không dùng WithoutModelEvents vì model sản phẩm tự tính % giảm giá khi lưu.)
     */
    public function run(): void
    {
        Storage::disk('public')->deleteDirectory('seed');
        Cache::flush();

        $this->call([
            UserSeeder::class,
            CatalogSeeder::class,
            ContentSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
