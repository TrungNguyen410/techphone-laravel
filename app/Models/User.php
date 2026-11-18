<?php

namespace App\Models;

use App\Support\ShippingAddress;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'full_name', 'email', 'phone', 'password', 'role', 'status', 'address', 'shipping_address', 'avatar',
    'phone_verified_at', 'auth_provider', 'firebase_uid',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'shipping_address' => 'array',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'assigned_user_id');
    }

    public function scopeCustomers(Builder $query): void
    {
        $query->where('role', 'customer');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Tài khoản đăng nhập bằng Google/Facebook (không có mật khẩu, có thể chưa có số điện thoại). */
    public function isSocialAccount(): bool
    {
        return in_array($this->auth_provider, ['google', 'facebook'], true);
    }

    public function authProviderLabel(): string
    {
        return match ($this->auth_provider) {
            'google' => 'Google',
            'facebook' => 'Facebook',
            default => 'Số điện thoại',
        };
    }

    /**
     * Địa chỉ nhận hàng đã lưu, chỉ trả về khi đủ thông tin để giao hàng.
     *
     * @return array{full_name: string, email: string, phone: string, address: string, province: string, ward: string}|null
     */
    public function savedShippingAddress(): ?array
    {
        return ShippingAddress::isComplete($this->shipping_address) ? ShippingAddress::from($this->shipping_address) : null;
    }

    /**
     * Tên ngắn hiển thị trên header: hai từ cuối của họ tên.
     */
    public function shortName(): string
    {
        $words = preg_split('/\s+/', trim($this->full_name ?: (string) $this->email));

        return implode(' ', array_slice($words, -2));
    }
}
