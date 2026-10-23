<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Cấu hình cửa hàng dạng key/value (tên shop, hotline, mạng xã hội...), có cache.
 */
class SettingService
{
    public const DEFAULTS = [
        'storeName' => 'TechPhone',
        'hotline' => '1900 6868',
        'email' => 'support@techphone.vn',
        'address' => '123 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh',
        'logo' => '',
        'facebook' => 'https://facebook.com/techphone',
        'instagram' => 'https://instagram.com/techphone',
        'youtube' => 'https://youtube.com/@techphone',
        'zaloUrl' => '',
        'freeShippingThreshold' => 10000000,
    ];

    private const CACHE_KEY = 'store-settings';

    /** @var array<string, mixed>|null Bộ nhớ tạm trong một request */
    private ?array $loaded = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
        } catch (QueryException) {
            // Chưa chạy migration hoặc mất kết nối CSDL: vẫn hiển thị được giao diện với giá trị mặc định.
            return self::DEFAULTS;
        }

        return $this->loaded = array_merge(self::DEFAULTS, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** @param array<string, mixed> $values */
    public function save(array $values, string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }
}
