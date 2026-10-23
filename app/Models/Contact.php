<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['full_name', 'email', 'phone', 'subject', 'message', 'status', 'admin_note'])]
class Contact extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'new' => 'Mới',
        'read' => 'Đang xử lý',
        'resolved' => 'Đã giải quyết',
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
