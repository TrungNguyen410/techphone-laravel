<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    /**
     * Kiểm tra mã giảm giá cho một đơn có tạm tính $subtotal (chưa giữ lượt dùng).
     */
    public function validate(string $code, int $subtotal): Voucher
    {
        $voucher = Voucher::where('code', strtoupper(trim($code)))->first();

        if (! $voucher || ! $voucher->active) {
            throw new BusinessException('Mã giảm giá không hợp lệ');
        }
        if ($voucher->quantity && $voucher->used >= $voucher->quantity) {
            throw new BusinessException('Mã giảm giá đã hết lượt sử dụng');
        }
        if ($subtotal < $voucher->min_order) {
            throw new BusinessException('Đơn hàng chưa đạt giá trị tối thiểu để dùng mã giảm giá');
        }

        $today = now()->startOfDay();
        if ($today->lt($voucher->start_date) || $today->gt($voucher->end_date)) {
            throw new BusinessException('Mã giảm giá chưa đến hạn hoặc đã hết hạn sử dụng');
        }

        return $voucher;
    }

    /**
     * Giữ một lượt dùng bằng câu UPDATE có điều kiện (an toàn khi nhiều người đặt cùng lúc).
     * Phải được gọi bên trong transaction tạo đơn.
     */
    public function reserve(string $code, int $subtotal): Voucher
    {
        $code = strtoupper(trim($code));
        $today = now()->toDateString();

        $updated = Voucher::where('code', $code)
            ->where('active', true)
            ->where('min_order', '<=', $subtotal)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where(fn ($query) => $query->where('quantity', 0)->orWhereColumn('used', '<', 'quantity'))
            ->update(['used' => DB::raw('used + 1')]);

        if ($updated === 0) {
            $this->validate($code, $subtotal);

            throw new BusinessException('Không thể giữ lượt sử dụng mã giảm giá', 409);
        }

        return Voucher::where('code', $code)->firstOrFail();
    }

    /** Trả lại lượt dùng khi đơn bị hủy. */
    public function release(?string $code): void
    {
        if (! $code) {
            return;
        }

        Voucher::withTrashed()
            ->where('code', strtoupper($code))
            ->where('used', '>', 0)
            ->update(['used' => DB::raw('used - 1')]);
    }
}
