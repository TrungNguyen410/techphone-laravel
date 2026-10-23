<?php

namespace Tests\Unit;

use App\Models\Voucher;
use App\Services\ShippingService;
use App\Support\VietnamesePhone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Các quy tắc tính tiền thuần (không cần CSDL): phí ship, giảm giá voucher, chuẩn hóa SĐT.
 */
class PricingTest extends TestCase
{
    public static function shippingCases(): array
    {
        return [
            'đơn từ 10 triệu được miễn phí' => ['Hà Nội', 10000000, 0, 1],
            'TP.HCM' => ['TP. Hồ Chí Minh', 500000, 20000, 1],
            'Hà Nội' => ['Hà Nội', 500000, 30000, 2],
            'Đà Nẵng' => ['Đà Nẵng', 500000, 30000, 2],
            'tỉnh khác' => ['Cà Mau', 500000, 40000, 4],
            'chưa chọn tỉnh' => [null, 500000, 40000, 4],
        ];
    }

    #[DataProvider('shippingCases')]
    public function test_shipping_quote(?string $province, int $subtotal, int $fee, int $days): void
    {
        $quote = (new ShippingService)->quote($province, $subtotal);

        $this->assertSame($fee, $quote['fee']);
        $this->assertSame($days, $quote['days']);
    }

    public function test_percent_voucher_is_capped_by_max_discount(): void
    {
        $voucher = new Voucher(['type' => 'percent', 'value' => 10, 'min_order' => 5000000, 'max_discount' => 1000000]);

        $this->assertSame(600000, $voucher->discountFor(6000000, 30000));
        $this->assertSame(1000000, $voucher->discountFor(30000000, 0));
        $this->assertSame(0, $voucher->discountFor(4000000, 30000), 'Chưa đạt giá trị tối thiểu');
    }

    public function test_fixed_and_shipping_vouchers(): void
    {
        $fixed = new Voucher(['type' => 'fixed', 'value' => 200000, 'min_order' => 0, 'max_discount' => 0]);
        $shipping = new Voucher(['type' => 'shipping', 'value' => 30000, 'min_order' => 0, 'max_discount' => 0]);

        $this->assertSame(200000, $fixed->discountFor(3000000, 0));
        $this->assertSame(150000, $fixed->discountFor(150000, 0), 'Không giảm quá tạm tính');
        $this->assertSame(20000, $shipping->discountFor(1000000, 20000), 'Không giảm quá phí ship');
        $this->assertSame(0, $shipping->discountFor(20000000, 0));
    }

    public static function phoneCases(): array
    {
        return [
            ['0912345678', '0912345678'],
            ['0912 345 678', '0912345678'],
            ['+84912345678', '0912345678'],
            ['84912345678', '0912345678'],
            ['0212345678', null],
            ['091234567', null],
            ['abc', null],
        ];
    }

    #[DataProvider('phoneCases')]
    public function test_vietnamese_phone_normalization(string $input, ?string $expected): void
    {
        $this->assertSame($expected, VietnamesePhone::normalize($input));
    }

    public function test_phone_masking(): void
    {
        $this->assertSame('091***678', VietnamesePhone::mask('0912345678'));
        $this->assertSame('+84912345678', VietnamesePhone::toE164('0912345678'));
    }
}
