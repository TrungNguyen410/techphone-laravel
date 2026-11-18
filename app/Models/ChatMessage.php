<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_session_id', 'role', 'content', 'products', 'tool_calls', 'source', 'feedback', 'latency_ms'])]
class ChatMessage extends Model
{
    public const SOURCE_LABELS = ['gemini' => 'Gemini', 'offline' => 'Offline', 'fallback' => 'Dự phòng'];

    protected function casts(): array
    {
        return [
            'products' => 'array',
            'tool_calls' => 'array',
            'latency_ms' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    /** Dữ liệu gửi cho khung chat trên trình duyệt. */
    public function toClient(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'products' => $this->products ?? [],
            'feedback' => $this->feedback,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
