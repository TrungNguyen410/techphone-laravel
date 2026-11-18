<?php

namespace App\Support;

/**
 * Danh sách phường/xã/đặc khu của 34 tỉnh/thành sau sắp xếp đơn vị hành chính (hiệu lực 01/07/2025,
 * không còn cấp quận/huyện). Dữ liệu: public/data/vn-wards.json (nguồn: provinces.open-api.vn v2),
 * trình duyệt cũng tải file này để gợi ý khi khách gõ tên phường/xã.
 */
final class WardDirectory
{
    public const DATA_FILE = 'data/vn-wards.json';

    /** @var array<string, array<int, string>>|null */
    private static ?array $data = null;

    /** @return array<string, array<int, string>> */
    public static function all(): array
    {
        if (self::$data === null) {
            $path = public_path(self::DATA_FILE);
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            self::$data = is_array($decoded) ? $decoded : [];
        }

        return self::$data;
    }

    /** @return array<int, string> */
    public static function wardsOf(?string $province): array
    {
        return self::all()[(string) $province] ?? [];
    }

    /**
     * Tên chính thức của phường/xã khách gõ ("sai gon" → "Phường Sài Gòn"), hoặc null nếu không thuộc tỉnh đó.
     * Tên ngắn trùng nhau giữa phường và xã (không ghi rõ "Phường"/"Xã") thì không tự chọn.
     */
    public static function match(?string $province, ?string $ward): ?string
    {
        $wards = self::wardsOf($province);
        $typed = self::fold($ward);
        if ($typed === '' || $wards === []) {
            return null;
        }

        foreach ($wards as $official) {
            if (self::fold($official) === $typed) {
                return $official;
            }
        }
        if (preg_match('/^(phuong|xa|dac khu)\s/', $typed)) {
            return null;
        }

        $candidates = array_values(array_filter($wards, fn (string $official) => self::withoutUnit($official) === $typed));

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    private static function fold(?string $value): string
    {
        $text = Vietnamese::fold(str_replace(['’', '‘'], "'", (string) $value));

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function withoutUnit(string $value): string
    {
        return (string) preg_replace('/^(phuong|xa|dac khu)\s+/', '', self::fold($value));
    }
}
