<?php

namespace App\Support;

/**
 * Thông tin người nhận khách có thể lưu vào tài khoản cho lần đặt hàng sau.
 * Từ 01/07/2025 địa chỉ chỉ còn tỉnh/thành + phường/xã (không còn quận/huyện).
 */
final class ShippingAddress
{
    public const FIELDS = ['full_name', 'email', 'phone', 'address', 'province', 'ward'];

    /**
     * @param  array<string, mixed>|null  $source
     * @return array{full_name: string, email: string, phone: string, address: string, province: string, ward: string}
     */
    public static function from(?array $source): array
    {
        $address = [];
        foreach (self::FIELDS as $field) {
            $address[$field] = trim((string) ($source[$field] ?? ''));
        }
        $address['email'] = mb_strtolower($address['email']);
        $address['phone'] = VietnamesePhone::normalize($address['phone']) ?? '';

        return $address;
    }

    /** Đủ mọi trường (kể cả email) thì trang thanh toán mới bỏ qua được form địa chỉ. */
    public static function isComplete(?array $address): bool
    {
        if (! $address) {
            return false;
        }
        $address = self::from($address);
        foreach (self::FIELDS as $field) {
            if ($address[$field] === '') {
                return false;
            }
        }

        return true;
    }

    /** "12 Lê Lợi, Phường Sài Gòn, TP. Hồ Chí Minh" */
    public static function format(?array $address): string
    {
        $address = self::from($address);

        return implode(', ', array_filter([$address['address'], $address['ward'], $address['province']]));
    }
}
