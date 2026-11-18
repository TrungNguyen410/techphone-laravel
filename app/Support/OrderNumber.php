<?php

namespace App\Support;

/**
 * Mã đơn hàng dạng "TP" + yymmdd + số thứ tự trong ngày (đơn mẫu cũ có thể ngắn hơn).
 */
final class OrderNumber
{
    /**
     * Không chặn bên trái vì ngân hàng đôi khi dính liền nội dung chuyển khoản ("thanhtoanTP2610210001");
     * chặn bên phải để "TP26102510000" (đơn thứ 10000) không bị đọc thành đơn 1000.
     */
    public const PATTERN = '/TP\d{6,12}(?!\d)/i';

    /** Tìm mã đơn đầu tiên trong các đoạn văn bản (nội dung chuyển khoản, tin nhắn chatbot...). */
    public static function extract(?string ...$texts): string
    {
        $haystack = implode(' ', array_filter($texts, fn ($text) => $text !== null && $text !== ''));

        return preg_match(self::PATTERN, $haystack, $match) ? strtoupper($match[0]) : '';
    }
}
