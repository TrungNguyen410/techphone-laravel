<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['target', 'channel', 'purpose', 'code_hash', 'payload', 'attempts', 'expires_at', 'consumed_at'])]
#[Hidden(['code_hash'])]
class VerificationCode extends Model
{
    protected function casts(): array
    {
        return [
            // Payload đăng ký chứa mật khẩu đã băm nên được mã hóa thêm khi lưu.
            'payload' => 'encrypted:array',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
