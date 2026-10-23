<?php

namespace App\Services\Payments;

use Illuminate\Support\Carbon;

/**
 * Tích hợp cổng VNPay phiên bản 2.1.0: tạo URL thanh toán có chữ ký HMAC-SHA512 và kiểm tra chữ ký trả về.
 */
class VnpayGateway
{
    public function isConfigured(): bool
    {
        return filled(config('services.vnpay.tmn_code')) && filled(config('services.vnpay.hash_secret'));
    }

    public function createPaymentUrl(int $amount, string $reference, string $orderInfo, ?string $ipAddress, ?Carbon $now = null): string
    {
        $now = ($now ?? now())->copy()->timezone('Asia/Ho_Chi_Minh');

        $params = [
            'vnp_Version' => config('services.vnpay.version'),
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => config('services.vnpay.tmn_code'),
            'vnp_Amount' => $amount * 100,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $reference,
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => config('services.vnpay.return_url') ?: route('payments.vnpay.return'),
            'vnp_IpAddr' => $ipAddress ?: '127.0.0.1',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_ExpireDate' => $now->copy()->addMinutes(15)->format('YmdHis'),
        ];

        $query = $this->serialize($params);

        return config('services.vnpay.url').'?'.$query.'&vnp_SecureHash='.$this->sign($query);
    }

    /**
     * Kiểm tra chữ ký của dữ liệu VNPay gửi về (Return URL hoặc IPN).
     *
     * @param  array<string, mixed>  $query
     */
    public function verify(array $query): bool
    {
        $received = (string) ($query['vnp_SecureHash'] ?? '');
        if ($received === '' || ! $this->isConfigured()) {
            return false;
        }

        $params = array_filter(
            $query,
            fn ($value, $key) => str_starts_with($key, 'vnp_') && ! in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true),
            ARRAY_FILTER_USE_BOTH,
        );

        return hash_equals($this->sign($this->serialize($params)), strtolower($received));
    }

    /** @param array<string, mixed> $params */
    private function serialize(array $params): string
    {
        $params = array_filter($params, fn ($value) => $value !== null && $value !== '');
        ksort($params);

        return http_build_query($params);
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha512', $data, (string) config('services.vnpay.hash_secret'));
    }
}
