<?php

namespace App\Support\Chat;

/**
 * Đọc ngân sách của khách từ câu hỏi đã bỏ dấu, chữ thường (xem Vietnamese::fold):
 * "trên 20 triệu" → minPrice, "dưới 20tr" → maxPrice, "15-20 củ" → cả hai,
 * "tầm 20 triệu" → 80%–100%, chỉ nói "20 triệu" → 100%–110%.
 */
final class BudgetParser
{
    private const MILLION = 1000000;

    private const UNITS = ['trieu' => self::MILLION, 'tr' => self::MILLION, 'cu' => self::MILLION, 'm' => self::MILLION, 'k' => 1000, 'nghin' => 1000, 'ngan' => 1000];

    private const UNIT = '(trieu|tr|cu|m|k|nghin|ngan)(?![a-z])';

    private const NUMBER = '(\d+(?:[.,]\d+)?)';

    // Số không dính liền chữ phía trước, nên "s25", "giam200k" hay "tp260601" không bị đọc thành giá tiền.
    private const START = '(?<![a-z\d.,])';

    private const HALF = '(\s*ruoi(?![a-z]))?';

    /** Cụm dài đứng trước để "it hon" (ít hơn) thắng "hon" (hơn). */
    private const BEFORE = [
        ['khong vuot qua', 'max'], ['khong qua', 'max'], ['toi da', 'max'], ['it hon', 'max'], ['nho hon', 'max'],
        ['be hon', 'max'], ['thap hon', 'max'], ['re hon', 'max'], ['duoi', 'max'],
        ['lon hon', 'min'], ['cao hon', 'min'], ['nhieu hon', 'min'], ['it nhat', 'min'], ['toi thieu', 'min'],
        ['tren', 'min'], ['ngoai', 'min'], ['hon', 'min'], ['tu', 'min'],
        ['xap xi', 'approx'], ['khoang', 'approx'], ['quanh', 'approx'], ['tam', 'approx'], ['chung', 'approx'], ['co', 'approx'],
    ];

    private const AFTER = [
        ['tro len', 'min'], ['do len', 'min'], ['tro di', 'min'],
        ['tro xuong', 'max'], ['do lai', 'max'], ['tro lai', 'max'], ['doi lai', 'max'],
    ];

    /** @return array{minPrice?: int, maxPrice?: int} */
    public static function parse(string $folded): array
    {
        $text = self::prepare($folded);
        $money = self::findMoney($text);
        if (! $money) {
            return [];
        }
        if ($money['kind'] === 'range') {
            return ['minPrice' => min($money['low'], $money['high']), 'maxPrice' => max($money['low'], $money['high'])];
        }

        $direction = self::directionAfter(substr($text, $money['end'])) ?? self::directionBefore(substr($text, 0, $money['index']));

        return match ($direction) {
            'min' => ['minPrice' => $money['value']],
            'max' => ['maxPrice' => $money['value']],
            'approx' => ['minPrice' => (int) round($money['value'] * 0.8), 'maxPrice' => $money['value']],
            default => ['minPrice' => $money['value'], 'maxPrice' => (int) round($money['value'] * 1.1)],
        };
    }

    /** Số tiền đầu tiên, không quan tâm hướng, ví dụ giá trị đơn trong "mã X cho đơn 6 triệu". */
    public static function amount(string $folded): ?int
    {
        $money = self::findMoney(self::prepare($folded));
        if (! $money) {
            return null;
        }

        return $money['kind'] === 'range' ? $money['high'] : $money['value'];
    }

    /**
     * Giá do mô hình AI truyền vào: số dưới 1000 được hiểu là triệu ("20" → 20.000.000), giá trị sai bị bỏ.
     *
     * @return array{minPrice?: int, maxPrice?: int}
     */
    public static function normalizeArgs(array $args): array
    {
        $clean = function (mixed $value): ?int {
            if (! is_numeric($value) || (float) $value <= 0) {
                return null;
            }
            $number = (float) $value;

            return (int) round($number < 1000 ? $number * self::MILLION : $number);
        };
        $low = $clean($args['minPrice'] ?? null);
        $high = $clean($args['maxPrice'] ?? null);
        if ($low !== null && $high !== null && $low > $high) {
            [$low, $high] = [$high, $low];
        }

        return array_filter(['minPrice' => $low, 'maxPrice' => $high], fn ($value) => $value !== null);
    }

    /** Bỏ mọi đoạn nói về tiền để chữ số và đơn vị không lọt vào từ khóa tìm sản phẩm. */
    public static function strip(string $folded): string
    {
        $text = self::prepare($folded);
        foreach ([self::rangePattern(), self::amountPattern(), self::fullPattern()] as $pattern) {
            $text = (string) preg_replace($pattern, ' ', $text);
        }

        return $text;
    }

    private static function rangePattern(): string
    {
        return '/'.self::START.self::NUMBER.'\s*(?:'.self::UNIT.')?\s*(?:-|–|—|den|toi)\s*'.self::NUMBER.'\s*'.self::UNIT.self::HALF.'/u';
    }

    private static function amountPattern(): string
    {
        return '/'.self::START.self::NUMBER.'\s*'.self::UNIT.self::HALF.'/u';
    }

    /** Số tiền đầy đủ bằng đồng: "20.000.000", "20,000,000", "20000000đ". */
    private static function fullPattern(): string
    {
        return '/(?<![a-z\d.,])(\d{1,3}(?:[.,]\d{3}){2,}|\d{6,})(?!\d)(?:\s*(?:d|dong|vnd)(?![a-z]))?/u';
    }

    /** Từ chỉ hướng viết dính số ("tren20tr") được tách ra để đọc được con số. */
    private static function prepare(string $text): string
    {
        return (string) preg_replace('/(duoi|tren|tam|khoang|hon|tu|quanh)(\d)/', '$1 $2', $text);
    }

    private static function toNumber(string $digits): float
    {
        // Giống bản JavaScript: chỉ dấu phẩy đầu tiên được hiểu là dấu thập phân.
        $position = strpos($digits, ',');

        return (float) ($position === false ? $digits : substr_replace($digits, '.', $position, 1));
    }

    private static function amountOf(string $digits, ?string $unit, ?string $half = null): int
    {
        return (int) round((self::toNumber($digits) + ($half ? 0.5 : 0)) * (self::UNITS[$unit] ?? 1));
    }

    /** @return array<string, mixed>|null Lần nhắc tới tiền sớm nhất; cùng vị trí thì khoảng giá thắng một con số. */
    private static function findMoney(string $text): ?array
    {
        $flags = PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL;
        $found = [];

        if (preg_match(self::rangePattern(), $text, $range, $flags)) {
            $found[] = [
                'kind' => 'range',
                'index' => $range[0][1],
                'end' => $range[0][1] + strlen($range[0][0]),
                'low' => self::amountOf($range[1][0], $range[2][0] ?? $range[4][0]),
                'high' => self::amountOf($range[3][0], $range[4][0], $range[5][0] ?? null),
            ];
        }
        if (preg_match(self::amountPattern(), $text, $single, $flags)) {
            $found[] = [
                'kind' => 'single',
                'index' => $single[0][1],
                'end' => $single[0][1] + strlen($single[0][0]),
                'value' => self::amountOf($single[1][0], $single[2][0], $single[3][0] ?? null),
            ];
        }
        if (preg_match(self::fullPattern(), $text, $full, $flags)) {
            $found[] = [
                'kind' => 'single',
                'index' => $full[0][1],
                'end' => $full[0][1] + strlen($full[0][0]),
                'value' => (int) str_replace(['.', ','], '', $full[1][0]),
            ];
        }
        if (! $found) {
            return null;
        }

        usort($found, fn ($a, $b) => $a['index'] <=> $b['index'] ?: ($a['kind'] === 'range' ? -1 : ($b['kind'] === 'range' ? 1 : 0)));

        return $found[0];
    }

    private static function directionBefore(string $prefix): ?string
    {
        $tail = (string) preg_replace('/[\s,:]+$/', '', $prefix);
        foreach (self::BEFORE as [$phrase, $direction]) {
            if (preg_match('/(^|[^a-z])'.$phrase.'$/', $tail)) {
                return $direction;
            }
        }

        return null;
    }

    private static function directionAfter(string $suffix): ?string
    {
        $head = (string) preg_replace('/^[\s,.]+/', '', $suffix);
        foreach (self::AFTER as [$phrase, $direction]) {
            if (preg_match('/^'.$phrase.'($|[^a-z])/', $head)) {
                return $direction;
            }
        }

        return null;
    }
}
