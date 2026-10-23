<?php

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Models\VerificationCode;
use App\Support\VietnamesePhone;

/**
 * Sinh, gửi và xác minh mã OTP 6 số (hiệu lực 10 phút, tối đa 5 lần nhập sai, dùng một lần).
 */
class OtpService
{
    private const TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TwilioSmsSender $sms) {}

    /**
     * @return array{delivery_target: string, debug_otp?: string}
     */
    public function send(string $phone, string $purpose, ?array $payload = null): array
    {
        $code = (string) random_int(100000, 999999);

        VerificationCode::where('target', $phone)->where('purpose', $purpose)->whereNull('consumed_at')->delete();
        $verification = VerificationCode::create([
            'target' => $phone,
            'channel' => 'sms',
            'purpose' => $purpose,
            'code_hash' => $this->hash($code, $phone, $purpose),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        $result = ['delivery_target' => VietnamesePhone::mask($phone)];

        if ($this->sms->isConfigured()) {
            try {
                $this->sms->send($phone, "Mã xác minh TechPhone: {$code}");
            } catch (BusinessException $exception) {
                $verification->delete();
                throw $exception;
            }
        } elseif (app()->isProduction()) {
            $verification->delete();
            throw new BusinessException('Dịch vụ SMS chưa được cấu hình. Vui lòng thử lại sau.', 503);
        } else {
            // Môi trường phát triển: trả mã về để hiển thị thay cho SMS thật.
            $result['debug_otp'] = $code;
        }

        return $result;
    }

    public function verify(string $phone, string $purpose, string $otp): VerificationCode
    {
        $verification = VerificationCode::where('target', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $verification || $verification->attempts >= self::MAX_ATTEMPTS) {
            throw new BusinessException('Mã OTP không hợp lệ hoặc đã hết hạn', 400);
        }

        if (! hash_equals($verification->code_hash, $this->hash($otp, $phone, $purpose))) {
            $verification->increment('attempts');
            throw new BusinessException('Mã OTP không hợp lệ hoặc đã hết hạn', 400);
        }

        $consumed = VerificationCode::whereKey($verification->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        if ($consumed === 0) {
            throw new BusinessException('Mã OTP không hợp lệ hoặc đã hết hạn', 400);
        }

        return $verification;
    }

    private function hash(string $code, string $target, string $purpose): string
    {
        return hash_hmac('sha256', "{$purpose}:{$target}:{$code}", (string) config('app.key'));
    }
}
