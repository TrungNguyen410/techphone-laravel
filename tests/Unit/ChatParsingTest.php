<?php

namespace Tests\Unit;

use App\Support\Chat\BudgetParser;
use App\Support\Chat\CustomerNeeds;
use App\Support\OrderNumber;
use App\Support\Vietnamese;
use App\Support\WardDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bộ đọc ngân sách, nhu cầu, mã đơn và phường/xã của chatbot/địa chỉ.
 * tests/Fixtures/budget-cases.json lấy nguyên từ bản Node.js để hai bản đọc giá giống hệt nhau.
 */
class ChatParsingTest extends TestCase
{
    public static function budgetCases(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/budget-cases.json'), true);

        return collect($cases)->mapWithKeys(fn ($case) => [$case['input'] => [$case['input'], $case['expected']]])->all();
    }

    #[DataProvider('budgetCases')]
    public function test_budget_phrasings(string $input, array $expected): void
    {
        $this->assertEquals($expected, BudgetParser::parse(Vietnamese::fold($input)));
    }

    public function test_budget_fixture_covers_many_phrasings(): void
    {
        $this->assertGreaterThanOrEqual(40, count(self::budgetCases()));
    }

    public function test_first_amount_without_direction(): void
    {
        $this->assertSame(6000000, BudgetParser::amount(Vietnamese::fold('Mã TECH10 dùng cho đơn 6 triệu được không?')));
        $this->assertSame(6500000, BudgetParser::amount(Vietnamese::fold('đơn 6.500.000 có dùng FREESHIP được không')));
        $this->assertSame(7500000, BudgetParser::amount(Vietnamese::fold('đơn hàng 7 triệu rưỡi')));
        $this->assertNull(BudgetParser::amount(Vietnamese::fold('mã GIAM200K dùng được không')));
    }

    public function test_price_arguments_from_the_model(): void
    {
        $this->assertSame(['minPrice' => 20000000], BudgetParser::normalizeArgs(['minPrice' => 20]));
        $this->assertSame(['maxPrice' => 15500000], BudgetParser::normalizeArgs(['maxPrice' => 15.5]));
        $this->assertSame(['minPrice' => 20000000, 'maxPrice' => 25000000], BudgetParser::normalizeArgs(['minPrice' => 25000000, 'maxPrice' => 20000000]));
        $this->assertSame([], BudgetParser::normalizeArgs(['minPrice' => -1, 'maxPrice' => 'abc']));
        $this->assertSame(['maxPrice' => 18000000], BudgetParser::normalizeArgs(['minPrice' => 0, 'maxPrice' => '18000000']));
    }

    public function test_budget_is_stripped_from_keywords(): void
    {
        $this->assertStringNotContainsString('20', BudgetParser::strip(Vietnamese::fold('galaxy dưới 20 triệu')));
        $this->assertStringContainsString('s25', BudgetParser::strip(Vietnamese::fold('galaxy s25 dưới 20 triệu')));
    }

    public function test_customer_needs(): void
    {
        $need = fn (string $text) => CustomerNeeds::parse(Vietnamese::fold($text));
        $this->assertSame('battery', $need('máy pin trâu trên 20 triệu'));
        $this->assertSame('camera', $need('quay phim, selfie tốt'));
        $this->assertSame('gaming', $need('điện thoại chơi game mượt'));
        $this->assertSame('big_screen', $need('màn hình lớn để xem phim'));
        $this->assertSame('compact', $need('máy nhỏ gọn cầm vừa tay'));
        $this->assertNull($need('nhỏ hơn 20 củ'));

        $phones = [
            (object) ['id' => 'a', 'battery' => '4500 mAh', 'camera' => '48MP', 'ram' => '8GB', 'screen' => '6.3 inch', 'price' => 20000000],
            (object) ['id' => 'b', 'battery' => '5340 mAh', 'camera' => '76MP', 'ram' => '16GB', 'screen' => '6.8 inch', 'price' => 25000000],
            (object) ['id' => 'c', 'battery' => '4980 mAh', 'camera' => '60MP', 'ram' => '16GB', 'screen' => '6.6 inch', 'price' => 30000000],
        ];
        $ids = fn (array $list) => array_map(fn ($phone) => $phone->id, $list);
        $this->assertSame(['b', 'c', 'a'], $ids(CustomerNeeds::rank($phones, 'battery')));
        $this->assertSame(['c', 'b', 'a'], $ids(CustomerNeeds::rank($phones, 'gaming')));
        $this->assertSame(['a', 'c', 'b'], $ids(CustomerNeeds::rank($phones, 'compact')));
        $this->assertSame('pin 5340 mAh', CustomerNeeds::detail($phones[1], 'battery'));
    }

    public function test_order_number_extraction(): void
    {
        $this->assertSame('TP2610210001', OrderNumber::extract('thanhtoanTP2610210001 chuyen tien'));
        $this->assertSame('TP260601', OrderNumber::extract(null, 'Don tp260601'));
        $this->assertSame('TP26102510000', OrderNumber::extract('TP26102510000'));
        $this->assertSame('', OrderNumber::extract('chuyen khoan mua hang'));
    }

    public function test_ward_names_after_2025_reorganisation(): void
    {
        $this->assertSame('Phường Sài Gòn', WardDirectory::match('TP. Hồ Chí Minh', 'sai gon'));
        $this->assertSame('Phường Sài Gòn', WardDirectory::match('TP. Hồ Chí Minh', 'Phường Sài Gòn'));
        $this->assertNull(WardDirectory::match('TP. Hồ Chí Minh', 'Bến Nghé'), 'Phường cũ đã sáp nhập');
        $this->assertNull(WardDirectory::match('Hà Nội', 'Sài Gòn'));
        $this->assertCount(34, WardDirectory::all());
    }
}
