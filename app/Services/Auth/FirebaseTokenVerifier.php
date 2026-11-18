<?php

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Kiểm tra Firebase ID token (đăng nhập Google/Facebook) ngay trên máy chủ, không cần thư viện ngoài:
 * chữ ký RS256 bằng khóa công khai Google công bố, cùng các claim aud/iss/exp/auth_time/sign_in_provider.
 */
class FirebaseTokenVerifier
{
    public const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    /** Lượt đăng nhập cũ hơn thời gian này không được đổi thành phiên đăng nhập. */
    private const MAX_SIGN_IN_AGE = 15 * 60;

    private const CLOCK_SKEW = 60;

    private const CACHE_KEY = 'firebase-securetoken-certs';

    public function isConfigured(): bool
    {
        return filled(config('services.firebase.project_id')) && filled(config('services.firebase.api_key'));
    }

    /**
     * @param  array<int, string>  $providers  Ví dụ ['google.com', 'facebook.com']
     * @return array<string, mixed> Claims đã được kiểm tra
     */
    public function verify(string $idToken, array $providers): array
    {
        $projectId = (string) config('services.firebase.project_id');
        if ($projectId === '') {
            throw new BusinessException('Đăng nhập bằng Google/Facebook chưa được cấu hình.', 503);
        }

        $segments = explode('.', $idToken);
        if (count($segments) !== 3) {
            throw $this->invalid();
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);
        $signature = $this->base64UrlDecode($encodedSignature);
        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || empty($header['kid']) || $signature === '') {
            throw $this->invalid();
        }

        $cert = $this->certFor((string) $header['kid']);
        $publicKey = $cert ? openssl_pkey_get_public($cert) : false;
        if (! $publicKey || openssl_verify($encodedHeader.'.'.$encodedPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw $this->invalid();
        }

        $now = time();
        $authTime = (int) ($claims['auth_time'] ?? 0);
        $valid = ($claims['aud'] ?? null) === $projectId
            && ($claims['iss'] ?? null) === 'https://securetoken.google.com/'.$projectId
            && (int) ($claims['exp'] ?? 0) > $now - self::CLOCK_SKEW
            && (int) ($claims['iat'] ?? PHP_INT_MAX) <= $now + self::CLOCK_SKEW
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== '' && strlen($claims['sub']) <= 128
            && $authTime > 0 && $authTime <= $now + self::CLOCK_SKEW && $now - $authTime <= self::MAX_SIGN_IN_AGE
            && in_array($claims['firebase']['sign_in_provider'] ?? null, $providers, true);
        if (! $valid) {
            throw $this->invalid();
        }

        return $claims;
    }

    /** Khóa theo kid; không thấy thì tải lại vì Google vừa xoay vòng khóa. */
    private function certFor(string $keyId): ?string
    {
        $certs = $this->certs();
        if (! isset($certs[$keyId])) {
            $certs = $this->certs(refresh: true);
        }

        return $certs[$keyId] ?? null;
    }

    /** @return array<string, string> */
    private function certs(bool $refresh = false): array
    {
        if (! $refresh && is_array($cached = Cache::get(self::CACHE_KEY))) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)->get(self::CERTS_URL);
        } catch (ConnectionException) {
            throw new BusinessException('Không thể kết nối dịch vụ đăng nhập Google/Facebook. Vui lòng thử lại sau.', 503);
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new BusinessException('Không thể kết nối dịch vụ đăng nhập Google/Facebook. Vui lòng thử lại sau.', 503);
        }

        $maxAge = preg_match('/max-age=(\d+)/', (string) $response->header('Cache-Control'), $match) ? (int) $match[1] : 3600;
        Cache::put(self::CACHE_KEY, $response->json(), max(60, $maxAge));

        return $response->json();
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? '' : $decoded;
    }

    private function invalid(): BusinessException
    {
        return new BusinessException('Đăng nhập Google/Facebook không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.', 401);
    }
}
