<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'visitor_id', 'title', 'message_count', 'last_message_at', 'context'])]
class ChatSession extends Model
{
    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
            'last_message_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    /** Chủ cuộc trò chuyện: tài khoản đang đăng nhập, hoặc trình duyệt khách (mã visitor) đã mở nó. */
    public function isOwnedBy(?User $user, ?string $visitorId): bool
    {
        $ownsAsUser = $user && $this->user_id === $user->id;
        $ownsAsVisitor = ! $this->user_id && $visitorId && hash_equals($this->visitor_id, $visitorId);

        return $ownsAsUser || $ownsAsVisitor;
    }

    public function customerName(): string
    {
        return $this->user_id ? ($this->user?->full_name ?? 'Khách hàng') : 'Khách vãng lai';
    }
}
