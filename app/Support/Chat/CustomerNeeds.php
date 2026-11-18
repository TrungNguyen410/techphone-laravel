<?php

namespace App\Support\Chat;

use App\Support\Vietnamese;

/**
 * Nhu cầu của khách ("pin trâu", "chụp ảnh đẹp"...) đọc từ câu đã bỏ dấu, và cách xếp hạng điện thoại theo nhu cầu.
 */
final class CustomerNeeds
{
    public const WORDS = [
        'battery' => ['pin trau', 'pin khoe', 'pin lau', 'pin tot', 'pin ben', 'dung lau'],
        'camera' => ['chup anh', 'chup hinh', 'camera', 'quay phim', 'selfie'],
        'gaming' => ['choi game', 'game', 'hieu nang', 'cau hinh manh', 'manh'],
        'big_screen' => ['man hinh lon', 'man hinh to', 'man lon', 'man to', 'xem phim'],
        'compact' => ['nho gon', 'gon nhe', 'cam vua tay', 'mot tay'],
    ];

    public const LABELS = [
        'battery' => 'pin trâu',
        'camera' => 'chụp ảnh đẹp',
        'gaming' => 'chơi game',
        'big_screen' => 'màn hình lớn',
        'compact' => 'nhỏ gọn',
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::WORDS);
    }

    /** Nhu cầu đầu tiên tìm thấy theo thứ tự trong WORDS. */
    public static function parse(string $folded): ?string
    {
        foreach (self::WORDS as $need => $words) {
            if (Vietnamese::hasAny($folded, $words)) {
                return $need;
            }
        }

        return null;
    }

    /** Bỏ các cụm chỉ nhu cầu để chúng không thành từ khóa tìm tên máy. */
    public static function strip(string $folded): string
    {
        foreach (array_merge(...array_values(self::WORDS)) as $word) {
            $folded = (string) preg_replace('/(^|[^a-z0-9])'.preg_quote($word, '/').'(?=$|[^a-z0-9])/', '$1 ', $folded);
        }

        return $folded;
    }

    /**
     * Điểm cao hơn = hợp nhu cầu hơn; bằng điểm thì giữ thứ tự cũ (bán chạy trước).
     *
     * @template T of object
     *
     * @param  iterable<T>  $phones
     * @return array<int, T>
     */
    public static function rank(iterable $phones, string $need): array
    {
        $list = [];
        foreach ($phones as $phone) {
            $list[] = $phone;
        }
        $score = match ($need) {
            'battery' => fn ($phone) => self::numberIn($phone->battery),
            'camera' => fn ($phone) => self::numberIn($phone->camera),
            'gaming' => fn ($phone) => self::numberIn($phone->ram) * 1e9 + (float) $phone->price,
            'big_screen' => fn ($phone) => self::numberIn($phone->screen),
            'compact' => fn ($phone) => -self::numberIn($phone->screen),
            default => null,
        };
        if (! $score) {
            return $list;
        }

        $scored = array_map(fn ($phone, $index) => [$phone, $index, $score($phone)], $list, array_keys($list));
        usort($scored, fn ($a, $b) => $b[2] <=> $a[2] ?: $a[1] <=> $b[1]);

        return array_column($scored, 0);
    }

    /** Thông số nêu đầu tiên trong câu trả lời, ví dụ "pin 5340 mAh" khi khách hỏi pin. */
    public static function detail(object $phone, ?string $need): string
    {
        return match ($need) {
            'battery' => $phone->battery ? 'pin '.$phone->battery : '',
            'camera' => $phone->camera ? 'camera '.$phone->camera : '',
            'gaming' => $phone->ram ? 'RAM '.$phone->ram : '',
            'big_screen', 'compact' => $phone->screen ? 'màn hình '.$phone->screen : '',
            default => '',
        };
    }

    private static function numberIn(?string $value): float
    {
        return preg_match('/\d+(?:\.\d+)?/', (string) $value, $match) ? (float) $match[0] : 0.0;
    }
}
