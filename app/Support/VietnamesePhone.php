<?php

namespace App\Support;

/**
 * Chuẩn hóa số di động Việt Nam về dạng 10 số bắt đầu bằng 03/05/07/08/09.
 */
class VietnamesePhone
{
    public const PATTERN = '/^0(?:3|5|7|8|9)\d{8}$/';

    public static function normalize(?string $value): ?string
    {
        $phone = preg_replace('/[\s().-]/', '', trim((string) $value));

        if (str_starts_with($phone, '+84')) {
            $phone = '0'.substr($phone, 3);
        } elseif (str_starts_with($phone, '84') && strlen($phone) === 11) {
            $phone = '0'.substr($phone, 2);
        }

        return preg_match(self::PATTERN, $phone) ? $phone : null;
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    public static function mask(?string $value): string
    {
        $phone = self::normalize($value) ?? (string) $value;

        return strlen($phone) >= 6 ? substr($phone, 0, 3).'***'.substr($phone, -3) : '***';
    }

    /** Định dạng E.164 dùng cho nhà cung cấp SMS: 0912345678 => +84912345678. */
    public static function toE164(string $phone): string
    {
        return '+84'.ltrim($phone, '0');
    }
}
