<?php

namespace App\Support;

use Normalizer;

/**
 * Chuẩn hóa tiếng Việt để so khớp: chữ thường, bỏ dấu, "đ" → "d" ("Sạc dự phòng" → "sac du phong").
 */
final class Vietnamese
{
    /** Bảng bỏ dấu dự phòng khi PHP không bật extension intl (ví dụ XAMPP mặc định). */
    private const LETTERS = [
        'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ', 'o' => 'òóọỏõôồốộổỗơờớợởỡ',
        'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
    ];

    /** @var array<string, string>|null */
    private static ?array $map = null;

    public static function fold(?string $value): string
    {
        $text = mb_strtolower((string) $value, 'UTF-8');
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_D) ?: $text;
            $text = preg_replace('/\p{Mn}+/u', '', $text) ?? $text;

            return str_replace('đ', 'd', $text);
        }

        if (self::$map === null) {
            self::$map = [];
            foreach (self::LETTERS as $base => $letters) {
                foreach (mb_str_split($letters) as $letter) {
                    self::$map[$letter] = $base;
                }
            }
        }

        return strtr($text, self::$map);
    }

    /**
     * Tách từ (đã bỏ dấu), bỏ các từ một ký tự.
     *
     * @return array<int, string>
     */
    public static function tokens(?string $value): array
    {
        $parts = preg_split('/[^a-z0-9]+/', self::fold($value)) ?: [];

        return array_values(array_filter($parts, fn (string $token) => strlen($token) > 1));
    }

    /** Có cụm từ nguyên vẹn trong văn bản đã chuẩn hóa ("danh sach" không khớp "sac"). */
    public static function hasPhrase(string $folded, string $phrase): bool
    {
        return (bool) preg_match('/(^|[^a-z0-9])'.preg_quote($phrase, '/').'($|[^a-z0-9])/', $folded);
    }

    /** @param array<int, string> $phrases */
    public static function hasAny(string $folded, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (self::hasPhrase($folded, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
