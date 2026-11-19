<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_logs_in_with_phone_in_any_format(): void
    {
        $user = User::factory()->create(['phone' => '0911111111']);

        $this->post('/login', ['identifier' => '+84 911 111 111', 'password' => '123456'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_is_sent_to_dashboard(): void
    {
        User::factory()->admin()->create(['phone' => '0900000000']);

        $this->post('/login', ['identifier' => '0900000000', 'password' => '123456'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_wrong_password_and_locked_account_are_rejected(): void
    {
        User::factory()->create(['phone' => '0911111111']);
        User::factory()->locked()->create(['phone' => '0933333333']);

        $this->post('/login', ['identifier' => '0911111111', 'password' => 'sai'])
            ->assertSessionHasErrors(['identifier' => 'Thông tin đăng nhập không chính xác']);
        $this->post('/login', ['identifier' => '0933333333', 'password' => '123456'])
            ->assertSessionHasErrors(['identifier' => 'Tài khoản đã bị khóa']);
        $this->assertGuest();
    }

    public function test_locked_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account')->assertOk();

        $user->update(['status' => 'locked']);

        $this->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_area_requires_admin_role(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/dashboard')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/dashboard')->assertOk();
    }

    public function test_registration_creates_account_without_otp(): void
    {
        $this->post('/register', [
            'full_name' => 'Khách Mới',
            'phone' => '+84 977 777 777',
            'password' => 'matkhau1',
            'password_confirmation' => 'matkhau1',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('toast', fn ($toast) => $toast['message'] === 'Đăng ký thành công. Vui lòng đăng nhập!');

        $user = User::where('phone', '0977777777')->first();
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('matkhau1', $user->password));
        $this->assertSame('customer', $user->role);
        $this->assertSame('phone', $user->auth_provider);
        $this->assertNull($user->phone_verified_at, 'Đăng ký không gửi SMS nên số điện thoại chưa được xác minh');
        $this->assertGuest();

        $this->post('/login', ['identifier' => '0977777777', 'password' => 'matkhau1'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_cannot_choose_role_or_status(): void
    {
        $this->post('/register', [
            'full_name' => 'Hacker', 'phone' => '0966666666', 'password' => '123456', 'password_confirmation' => '123456',
            'role' => 'admin', 'status' => 'locked',
        ]);

        $user = User::where('phone', '0966666666')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertSame('active', $user->status);
    }

    public function test_duplicate_phone_cannot_register(): void
    {
        User::factory()->create(['phone' => '0977777777']);

        $this->post('/register', ['full_name' => 'A', 'phone' => '0977777777', 'password' => '123456', 'password_confirmation' => '123456'])
            ->assertSessionHas('toast', fn ($toast) => $toast['message'] === 'Số điện thoại này đã được đăng ký');
    }

    public function test_password_reset_with_otp(): void
    {
        $user = User::factory()->create(['phone' => '0911111111']);

        $this->post('/forgot-password', ['identifier' => '0911111111'])->assertRedirect(route('password.reset'));
        $otp = session('otp_delivery')['debug_otp'];

        $this->post('/reset-password', ['otp' => $otp, 'password' => 'moi12345', 'password_confirmation' => 'moi12345'])
            ->assertRedirect(route('login'));

        $this->assertTrue(password_verify('moi12345', $user->fresh()->password));
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/password', ['current_password' => 'sai', 'password' => 'moi123', 'password_confirmation' => 'moi123'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/account/password', ['current_password' => '123456', 'password' => 'moi123', 'password_confirmation' => 'moi123'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('moi123', $user->fresh()->password));
    }
}
