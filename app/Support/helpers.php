<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

if (! function_exists('money')) {
    /**
     * Định dạng tiền Việt Nam: 33990000 => "33.990.000 ₫".
     */
    function money(int|float|null $value): string
    {
        return number_format((float) $value, 0, ',', '.').' ₫';
    }
}

if (! function_exists('format_date')) {
    function format_date(mixed $value, bool $withTime = false): string
    {
        if (! $value) {
            return '---';
        }

        $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        return $date->timezone(config('app.timezone'))->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
    }
}

if (! function_exists('media_url')) {
    /**
     * Đường dẫn ảnh: giữ nguyên URL tuyệt đối, còn đường dẫn tương đối lấy từ storage public.
     */
    function media_url(?string $path): string
    {
        if (! $path) {
            return asset('images/placeholder.svg');
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:', '/'])) {
            return $path;
        }

        return asset('storage/'.$path);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(App\Services\SettingService::class)->get($key, $default);
    }
}
