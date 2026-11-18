<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\FirebaseTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Đăng nhập Google/Facebook qua Firebase: máy chủ tự kiểm tra chữ ký RS256 và các claim của ID token.
 */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT = 'techphone-test';

    private \OpenSSLAsymmetricKey $key;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.firebase' => ['api_key' => 'web-key', 'auth_domain' => '', 'project_id' => self::PROJECT, 'app_id' => 'app']]);

        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'securetoken'], $this->key, ['digest_alg' => 'sha256']);
        openssl_x509_export(openssl_csr_sign($csr, null, $this->key, 1, ['digest_alg' => 'sha256']), $certificate);
        Http::fake([FirebaseTokenVerifier::CERTS_URL => Http::response(['kid-1' => $certificate], 200, ['Cache-Control' => 'max-age=3600'])]);
    }

    private function token(array $claims = [], string $kid = 'kid-1'): string
    {
        $encode = fn ($value) => rtrim(strtr(base64_encode(is_string($value) ? $value : json_encode($value)), '+/', '-_'), '=');
        $now = time();
        $payload = $claims + [
            'iss' => 'https://securetoken.google.com/'.self::PROJECT, 'aud' => self::PROJECT, 'sub' => 'firebase-uid-1',
            'iat' => $now, 'exp' => $now + 3600, 'auth_time' => $now, 'email' => 'social@example.com', 'email_verified' => true,
            'name' => 'Người Dùng Google', 'firebase' => ['sign_in_provider' => 'google.com'],
        ];
        $unsigned = $encode(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT']).'.'.$encode($payload);
        openssl_sign($unsigned, $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $unsigned.'.'.$encode($signature);
    }

    public function test_google_sign_in_creates_one_customer_account_without_phone(): void
    {
        $this->get('/login')->assertSee('Tiếp tục với Google')->assertSee('data-social-login', false);

        $this->postJson('/auth/social', ['id_token' => $this->token()])->assertOk()->assertJsonStructure(['redirect']);
        $user = User::firstWhere('firebase_uid', 'firebase-uid-1');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google', $user->auth_provider);
        $this->assertNull($user->phone);
        $this->assertSame('Người Dùng Google', $user->full_name);

        auth()->logout();
        $this->postJson('/auth/social', ['id_token' => $this->token()])->assertOk();
        $this->assertSame(1, User::where('firebase_uid', 'firebase-uid-1')->count());

        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Google')->assertDontSee('Đổi mật khẩu');
        $this->actingAs($user)->get('/account?tab=password')->assertOk()->assertSee('Thông tin cá nhân');
    }

    public function test_rejects_tampered_foreign_or_stale_tokens(): void
    {
        $this->postJson('/auth/social', ['id_token' => $this->token(['aud' => 'other-project'])])->assertUnauthorized();
        $this->postJson('/auth/social', ['id_token' => $this->token(['auth_time' => time() - 3600])])->assertUnauthorized();
        $this->postJson('/auth/social', ['id_token' => $this->token(['firebase' => ['sign_in_provider' => 'password']])])->assertUnauthorized();
        $this->postJson('/auth/social', ['id_token' => $this->token([], 'unknown-kid')])->assertUnauthorized();
        [$header, , $signature] = explode('.', $this->token());
        $forged = rtrim(strtr(base64_encode(json_encode(['sub' => 'attacker', 'aud' => self::PROJECT])), '+/', '-_'), '=');
        $this->postJson('/auth/social', ['id_token' => "{$header}.{$forged}.{$signature}"])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_never_merges_into_an_existing_email_and_respects_locked_accounts(): void
    {
        User::factory()->create(['email' => 'social@example.com']);
        $this->postJson('/auth/social', ['id_token' => $this->token()])->assertStatus(409);

        User::factory()->create(['firebase_uid' => 'firebase-uid-2', 'email' => null, 'phone' => null, 'auth_provider' => 'facebook', 'status' => 'locked']);
        $this->postJson('/auth/social', ['id_token' => $this->token(['sub' => 'firebase-uid-2', 'email' => '', 'firebase' => ['sign_in_provider' => 'facebook.com']])])
            ->assertForbidden();
        $this->assertGuest();
    }

    public function test_buttons_are_hidden_when_firebase_is_not_configured(): void
    {
        config(['services.firebase.project_id' => '', 'services.firebase.api_key' => '']);
        $this->get('/login')->assertOk()->assertDontSee('data-social-login', false);
        $this->postJson('/auth/social', ['id_token' => $this->token()])->assertStatus(503);
    }
}
