<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bài viết trong kho kiến thức chatbot (bảo hành, đổi trả, giao hàng...): cách "dạy" chatbot
 * thông tin cửa hàng mà không cần huấn luyện lại mô hình.
 */
#[Fillable(['title', 'category', 'content', 'keywords', 'active'])]
class KnowledgeArticle extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'warranty' => 'Bảo hành',
        'return' => 'Đổi trả',
        'shipping' => 'Giao hàng',
        'payment' => 'Thanh toán',
        'installment' => 'Trả góp',
        'store' => 'Cửa hàng',
        'faq' => 'Câu hỏi thường gặp',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'active' => 'boolean',
        ];
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /** Ô "Từ khóa" trong form quản trị: các từ khóa cách nhau bằng dấu phẩy. */
    public function getKeywordsTextAttribute(): string
    {
        return implode(', ', $this->keywords ?? []);
    }
}
