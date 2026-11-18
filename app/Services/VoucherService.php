<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    /** Bỏ các ký tự dễ nhầm (0/O, 1/I) để khách đọc và gõ lại mã không sai. */
    private const GIFT_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Kiểm tra mã giảm giá cho một đơn có tạm tính $subtotal (chưa giữ lượt dùng).
     * Voucher tặng riêng chỉ người được tặng dùng được; với người khác coi như mã không tồn tại.
     */
    public function validate(string $code, int $subtotal, ?User $user = null): Voucher
    {
        $voucher = Voucher::where('code', strtoupper(trim($code)))->first();

        if (! $voucher || ! $voucher->active) {
            throw new BusinessException('Mã giảm giá không hợp lệ');
        }
        if ($voucher->assigned_user_id && $voucher->assigned_user_id !== $user?->id) {
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
    public function reserve(string $code, int $subtotal, ?User $user = null): Voucher
    {
        $code = strtoupper(trim($code));
        $today = now()->toDateString();

        $updated = Voucher::where('code', $code)
            ->where('active', true)
            ->where('min_order', '<=', $subtotal)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where(fn ($query) => $query->whereNull('assigned_user_id')->orWhere('assigned_user_id', $user?->id ?? 0))
            ->where(fn ($query) => $query->where('quantity', 0)->orWhereColumn('used', '<', 'quantity'))
            ->update(['used' => DB::raw('used + 1')]);

        if ($updated === 0) {
            $this->validate($code, $subtotal, $user);

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

    /**
     * Voucher dùng 1 lần tặng riêng cho khách thân thiết (từ danh sách top khách hàng trên dashboard).
     *
     * @param  array{type: string, value: int, min_order?: ?int, max_discount?: ?int, end_date: string, note?: ?string}  $data
     */
    public function giftToCustomer(User $customer, array $data): Voucher
    {
        if ($customer->isAdmin()) {
            throw new BusinessException('Không tìm thấy khách hàng', 404);
        }
        $endDate = Carbon::parse($data['end_date'])->startOfDay();
        if ($endDate->lt(now()->startOfDay())) {
            throw new BusinessException('Hạn dùng phải từ hôm nay trở đi');
        }

        $note = trim((string) ($data['note'] ?? ''));

        return DB::transaction(function () use ($customer, $data, $endDate, $note) {
            do {
                $code = 'TRIAN'.collect(range(1, 6))->map(fn () => self::GIFT_CODE_ALPHABET[random_int(0, strlen(self::GIFT_CODE_ALPHABET) - 1)])->implode('');
            } while (Voucher::withTrashed()->where('code', $code)->exists());

            return Voucher::create([
                'code' => $code,
                'type' => $data['type'],
                'value' => (int) $data['value'],
                'min_order' => (int) ($data['min_order'] ?? 0),
                'max_discount' => $data['type'] === 'percent' ? (int) ($data['max_discount'] ?? 0) : 0,
                'quantity' => 1,
                'used' => 0,
                'start_date' => now()->toDateString(),
                'end_date' => $endDate->toDateString(),
                'active' => true,
                'assigned_user_id' => $customer->id,
                'note' => mb_substr($note !== '' ? $note : 'Tri ân khách hàng '.$customer->full_name, 0, 150),
            ]);
        });
    }

    /** @return Collection<int, Voucher> Voucher cửa hàng tặng riêng cho tài khoản này */
    public function giftsFor(User $user): Collection
    {
        return Voucher::where('assigned_user_id', $user->id)->latest('id')->get();
    }
}
