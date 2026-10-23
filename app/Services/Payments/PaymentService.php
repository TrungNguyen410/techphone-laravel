<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    private const PAYMENT_URL_TTL_MINUTES = 15;

    public function __construct(private readonly VnpayGateway $vnpay) {}

    /**
     * Các phương thức thanh toán đang bật và thông tin hiển thị (tài khoản ngân hàng, MoMo).
     *
     * @return array<string, array<string, mixed>>
     */
    public function providers(): array
    {
        $bank = config('services.bank');
        $momo = config('services.momo');

        return [
            'cod' => ['enabled' => true],
            'bank' => [
                'enabled' => filled($bank['name']) && filled($bank['bin']) && filled($bank['account_number']) && filled($bank['account_name']),
                'display' => $bank,
            ],
            'momo' => [
                'enabled' => filled($momo['phone']) && filled($momo['account_name']),
                'display' => $momo,
            ],
            'card' => ['enabled' => $this->vnpay->isConfigured()],
        ];
    }

    public function isEnabled(string $method): bool
    {
        return $this->providers()[$method]['enabled'] ?? false;
    }

    /**
     * Tạo (hoặc dùng lại trong 15 phút) giao dịch VNPay cho đơn và trả về URL chuyển hướng.
     */
    public function startVnpayCheckout(Order $order, ?string $ipAddress): string
    {
        if (! $this->vnpay->isConfigured()) {
            throw new BusinessException('VNPay chưa được cấu hình. Vui lòng thêm VNPAY_TMN_CODE và VNPAY_HASH_SECRET.', 503);
        }

        return DB::transaction(function () use ($order, $ipAddress) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled' || $order->isPaymentSettled()) {
                throw new BusinessException('Đơn hàng này không còn đủ điều kiện thanh toán', 409);
            }

            $pending = $order->transactions()->where('status', 'pending')->latest()->first();
            if ($pending && $pending->created_at->gt(now()->subMinutes(self::PAYMENT_URL_TTL_MINUTES))) {
                return $pending->payment_url;
            }
            $order->transactions()->where('status', 'pending')->update(['status' => 'expired']);

            $reference = $order->order_number.substr((string) now()->getTimestampMs(), -6).Str::lower(Str::random(6));
            $url = $this->vnpay->createPaymentUrl($order->total, $reference, 'Thanh toan don hang '.$order->order_number, $ipAddress);

            $order->transactions()->create([
                'provider' => 'vnpay',
                'method' => 'card',
                'amount' => $order->total,
                'reference' => $reference,
                'payment_url' => $url,
            ]);
            $order->update(['payment_reference' => $reference, 'payment_status' => 'pending']);

            return $url;
        });
    }

    /**
     * Xử lý kết quả VNPay (IPN hoặc Return URL đều có chữ ký nên dùng chung logic, chạy nhiều lần vẫn an toàn).
     *
     * @param  array<string, mixed>  $query
     * @return array{RspCode: string, Message: string, transaction?: PaymentTransaction}
     */
    public function settleVnpay(array $query): array
    {
        if (! $this->vnpay->verify($query)) {
            return ['RspCode' => '97', 'Message' => 'Chữ ký không hợp lệ'];
        }

        $paid = ($query['vnp_ResponseCode'] ?? '') === '00' && ($query['vnp_TransactionStatus'] ?? '') === '00';

        return DB::transaction(function () use ($query, $paid) {
            $transaction = PaymentTransaction::where('reference', $query['vnp_TxnRef'] ?? '')->lockForUpdate()->first();
            if (! $transaction) {
                return ['RspCode' => '01', 'Message' => 'Không tìm thấy đơn hàng'];
            }
            if ((int) ($query['vnp_Amount'] ?? 0) / 100 !== $transaction->amount) {
                return ['RspCode' => '04', 'Message' => 'Số tiền không hợp lệ', 'transaction' => $transaction];
            }

            $order = Order::whereKey($transaction->order_id)->lockForUpdate()->first();
            if (! $order) {
                return ['RspCode' => '01', 'Message' => 'Không tìm thấy đơn hàng'];
            }
            if ($transaction->status === 'paid' || $order->payment_status === 'paid') {
                return ['RspCode' => '02', 'Message' => 'Đơn hàng đã được xác nhận trước đó', 'transaction' => $transaction];
            }

            $transaction->update([
                'status' => $paid ? 'paid' : 'failed',
                'provider_transaction_id' => (string) ($query['vnp_TransactionNo'] ?? ''),
                'bank_code' => (string) ($query['vnp_BankCode'] ?? ''),
                'response_code' => (string) ($query['vnp_ResponseCode'] ?? ''),
                'raw_response' => $query,
                'paid_at' => $paid ? now() : null,
            ]);

            if ($paid) {
                // Đơn đã bị hủy mà tiền vẫn về: đánh dấu cần hoàn tiền thay vì xác nhận đơn.
                $order->update($order->status === 'cancelled'
                    ? ['payment_status' => 'refund_required']
                    : ['payment_status' => 'paid', 'status' => $order->status === 'pending' ? 'confirmed' : $order->status]);
                $order->transactions()->where('status', 'pending')->whereKeyNot($transaction->id)->update(['status' => 'expired']);
            } elseif ($order->status !== 'cancelled' && ! $order->transactions()->where('status', 'pending')->exists()) {
                $order->update(['payment_status' => 'failed']);
            }

            return ['RspCode' => '00', 'Message' => 'Confirm Success', 'transaction' => $transaction->fresh()];
        });
    }

    /**
     * Nhân viên đối soát thanh toán chuyển khoản/MoMo.
     */
    public function reconcileManual(Order $order, string $status, string $reference, string $note, User $actor): Order
    {
        if (! in_array($order->payment_method, ['bank', 'momo'], true)) {
            throw new BusinessException('Đơn hàng không dùng thanh toán thủ công', 409);
        }
        if (! in_array($order->payment_status, ['pending', 'failed'], true)) {
            throw new BusinessException('Thanh toán đã được đối soát', 409);
        }
        if ($status === $order->payment_status) {
            throw new BusinessException('Thanh toán đã ở trạng thái này', 409);
        }

        $order->update([
            'payment_status' => $status,
            'payment_reference' => $reference,
            'payment_confirmed_by' => $actor->id,
            'payment_confirmed_at' => now(),
            'payment_note' => $note,
            'status' => $status === 'paid' && $order->status === 'pending' ? 'confirmed' : $order->status,
        ]);

        return $order;
    }
}
