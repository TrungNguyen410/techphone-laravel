<?php

namespace App\Services\Payments;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\OrderNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Thanh toán chuyển khoản tự động qua webhook SePay: khi tiền về tài khoản, SePay gọi webhook với nội dung
 * chuyển khoản và số tiền; hệ thống tìm mã đơn trong nội dung, đối chiếu số tiền và xác nhận đơn.
 *
 * - Chống xử lý trùng: mỗi giao dịch SePay (id) chỉ được ghi một lần (reference SEPAY-{id} là duy nhất).
 * - Chuyển thiếu: ghi nhận từng lần, cộng dồn các lần chuyển của cùng đơn (hạn mức chuyển theo ngày).
 * - Đơn đã hủy mà tiền vẫn về: chuyển sang "Cần hoàn tiền"; tiền về thêm sau khi đã thanh toán: ghi chú để hoàn.
 */
class SepayWebhookService
{
    public function isConfigured(): bool
    {
        return filled(config('services.sepay.webhook_api_key'));
    }

    /** Header "Authorization: Apikey <SEPAY_WEBHOOK_API_KEY>" (so sánh an toàn theo thời gian). */
    public function assertAuthorized(?string $authorization): void
    {
        $apiKey = trim((string) config('services.sepay.webhook_api_key'));
        if ($apiKey === '') {
            throw new BusinessException('SePay chưa được cấu hình', 503);
        }
        if (! preg_match('/^Apikey\s+(.+)$/i', trim((string) $authorization), $match) || ! hash_equals($apiKey, trim($match[1]))) {
            throw new BusinessException('Webhook SePay không hợp lệ', 401);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload, ?string $authorization): array
    {
        $this->assertAuthorized($authorization);

        $transactionId = trim((string) ($payload['id'] ?? ''));
        $rawAmount = $payload['transferAmount'] ?? null;
        $amount = is_numeric($rawAmount) ? (int) round((float) $rawAmount) : 0;
        if ($transactionId === '' || $amount <= 0) {
            throw new BusinessException('Dữ liệu webhook SePay không hợp lệ', 400);
        }
        if (strtolower((string) ($payload['transferType'] ?? '')) !== 'in') {
            return ['matched' => false, 'reason' => 'not_incoming'];
        }

        $orderNumber = OrderNumber::extract(
            isset($payload['code']) ? (string) $payload['code'] : null,
            isset($payload['content']) ? (string) $payload['content'] : null,
            isset($payload['description']) ? (string) $payload['description'] : null,
        );
        if ($orderNumber === '') {
            return ['matched' => false, 'reason' => 'order_number_missing'];
        }

        $reference = 'SEPAY-'.$transactionId;
        $gateway = mb_substr((string) ($payload['gateway'] ?? ''), 0, 30);

        try {
            return DB::transaction(function () use ($payload, $amount, $orderNumber, $reference, $transactionId, $gateway) {
                if (PaymentTransaction::where('reference', $reference)->exists()) {
                    return ['matched' => true, 'duplicate' => true, 'orderNumber' => $orderNumber];
                }

                $order = Order::where('order_number', $orderNumber)->lockForUpdate()->first();
                if (! $order || $order->payment_method !== 'bank') {
                    return ['matched' => false, 'reason' => 'order_not_found', 'orderNumber' => $orderNumber];
                }

                $alreadySettled = $order->isPaymentSettled();
                $earlier = $alreadySettled ? collect() : $order->transactions()
                    ->where('provider', 'sepay')->where('response_code', 'UNDERPAID')->get(['amount']);
                $received = (int) $earlier->sum('amount') + $amount;
                $fullyPaid = ! $alreadySettled && $received >= $order->total;
                $responseCode = $alreadySettled ? 'OVERPAID' : ($fullyPaid ? 'MATCHED' : 'UNDERPAID');

                $order->transactions()->create([
                    'provider' => 'sepay',
                    'method' => 'bank',
                    'status' => $fullyPaid ? 'paid' : 'failed',
                    'amount' => $amount,
                    'reference' => $reference,
                    'provider_transaction_id' => mb_substr($transactionId, 0, 100),
                    'bank_code' => $gateway,
                    'response_code' => $responseCode,
                    'raw_response' => $payload,
                    'paid_at' => $fullyPaid ? now() : null,
                ]);

                if ($alreadySettled) {
                    // Ví dụ khách quét QR hai lần: ghi vào đơn để nhân viên hoàn lại phần tiền thừa.
                    $extra = sprintf('Nhận thêm %s (%s) sau khi đơn đã thanh toán, cần hoàn cho khách.', money($amount), $reference);
                    $order->update(['payment_note' => trim($order->payment_note."\n".$extra)]);

                    return ['matched' => true, 'orderNumber' => $orderNumber, 'paymentStatus' => $order->payment_status, 'overpaid' => true];
                }
                if (! $fullyPaid) {
                    return [
                        'matched' => true,
                        'orderNumber' => $orderNumber,
                        'paymentStatus' => $order->payment_status,
                        'underpaid' => true,
                        'received' => $received,
                        'remaining' => $order->total - $received,
                    ];
                }

                $cancelled = $order->status === 'cancelled';
                $via = $gateway !== '' ? $gateway : 'ngân hàng';
                $order->update([
                    'payment_status' => $cancelled ? 'refund_required' : 'paid',
                    'payment_reference' => $reference,
                    'payment_confirmed_by' => null,
                    'payment_confirmed_at' => now(),
                    'payment_note' => $earlier->isNotEmpty()
                        ? "Tự động xác nhận qua SePay ({$via}), đã nhận đủ qua ".($earlier->count() + 1).' lần chuyển'
                        : "Tự động xác nhận qua SePay ({$via})",
                    'status' => ! $cancelled && $order->status === 'pending' ? 'confirmed' : $order->status,
                ]);

                return ['matched' => true, 'orderNumber' => $orderNumber, 'paymentStatus' => $order->payment_status];
            });
        } catch (UniqueConstraintViolationException) {
            // SePay gửi lại cùng giao dịch song song: request thua coi như đã xử lý.
            return ['matched' => true, 'duplicate' => true, 'orderNumber' => $orderNumber];
        }
    }
}
