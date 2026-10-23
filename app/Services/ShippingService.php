<?php

namespace App\Services;

/**
 * Tính phí và thời gian giao hàng theo tỉnh/thành và giá trị đơn.
 */
class ShippingService
{
    public const PROVIDER = 'TechPhone Express';

    public const FREE_SHIPPING_THRESHOLD = 10000000;

    /** 34 tỉnh/thành sau sắp xếp đơn vị hành chính 2025. */
    public const PROVINCES = [
        'An Giang', 'Bắc Ninh', 'Cà Mau', 'Cao Bằng', 'Cần Thơ', 'Đà Nẵng', 'Đắk Lắk', 'Điện Biên', 'Đồng Nai',
        'Đồng Tháp', 'Gia Lai', 'Hà Nội', 'Hà Tĩnh', 'Hải Phòng', 'Hưng Yên', 'Huế', 'Khánh Hòa', 'Lai Châu',
        'Lâm Đồng', 'Lạng Sơn', 'Lào Cai', 'Nghệ An', 'Ninh Bình', 'Phú Thọ', 'Quảng Ngãi', 'Quảng Ninh',
        'Quảng Trị', 'Sơn La', 'Tây Ninh', 'Thái Nguyên', 'Thanh Hóa', 'TP. Hồ Chí Minh', 'Tuyên Quang', 'Vĩnh Long',
    ];

    /**
     * @return array{fee: int, days: int, eta: string}
     */
    public function quote(?string $province, int $subtotal): array
    {
        [$fee, $days] = match (true) {
            $subtotal >= self::FREE_SHIPPING_THRESHOLD => [0, 1],
            $province === 'TP. Hồ Chí Minh' => [20000, 1],
            in_array($province, ['Hà Nội', 'Đà Nẵng'], true) => [30000, 2],
            default => [40000, 4],
        };

        return ['fee' => $fee, 'days' => $days, 'eta' => $days.' ngày'];
    }
}
