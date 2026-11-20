<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ChatSession;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Models\VerificationCode;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * Xóa tài khoản khách theo yêu cầu (trang /xoa-du-lieu): xóa dữ liệu cá nhân, ẩn danh phần phải lưu lại.
 *
 * - Tài khoản: xóa mềm, đồng thời bỏ SĐT, email, Firebase UID nên số điện thoại / email / tài khoản
 *   Google-Facebook được giải phóng để đăng ký lại.
 * - Xóa hẳn: danh sách yêu thích, lịch sử chatbot, voucher tặng riêng chưa dùng, phiên đăng nhập, mã OTP.
 * - Ẩn danh (giữ để đối soát, kế toán): đơn hàng và đánh giá.
 */
class CustomerAccountService
{
    /** Đơn còn đang xử lý thì không xóa được, tránh mất thông tin giao hàng. */
    public const OPEN_ORDER_STATUSES = ['pending', 'confirmed', 'shipping'];

    public const DELETED_NAME = 'Tài khoản đã xóa';

    public function delete(User $user): void
    {
        if ($user->isAdmin()) {
            throw new BusinessException('Không tìm thấy khách hàng', 404);
        }
        if ($user->orders()->whereIn('status', self::OPEN_ORDER_STATUSES)->exists()) {
            throw new BusinessException('Khách còn đơn hàng đang xử lý. Hãy hoàn tất hoặc hủy các đơn đó trước khi xóa tài khoản.');
        }

        DB::transaction(function () use ($user) {
            $phone = $user->phone;

            $user->wishlistItems()->delete();
            ChatSession::where('user_id', $user->id)->delete(); // tin nhắn bị xóa theo (cascade)
            Voucher::withTrashed()->where('assigned_user_id', $user->id)->where('used', 0)->forceDelete();
            Voucher::withTrashed()->where('assigned_user_id', $user->id)->update(['assigned_user_id' => null, 'note' => '']);
            if ($phone) {
                VerificationCode::where('target', $phone)->delete();
            }

            Order::withTrashed()->where('user_id', $user->id)->update([
                'user_id' => null,
                'customer_name' => self::DELETED_NAME,
                'customer_email' => '',
                'customer_phone' => '',
                'customer_address' => '',
                'note' => null,
            ]);
            Review::withTrashed()->where('user_id', $user->id)->update(['user_id' => null, 'user_name' => 'Khách hàng']);

            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->forceFill([
                'full_name' => self::DELETED_NAME,
                'email' => null,
                'phone' => null,
                'firebase_uid' => null,
                'password' => null,
                'address' => '',
                'shipping_address' => null,
                'avatar' => '',
                'phone_verified_at' => null,
                'status' => 'inactive',
                'remember_token' => null,
            ])->save();
            $user->delete();
        });
    }
}
