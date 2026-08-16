<?php

namespace Tests\Feature;

use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    public function test_register_login_me_and_logout_with_bearer_tokens(): void
    {
        $registered = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '0901234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'customer');

        $registerToken = $registered->json('token');
        $this->withToken($registerToken)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'customer@example.com');

        $this->withToken($registerToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();
        $this->withToken($registerToken)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'Password123!',
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        $this->withToken($login->json('token'))->getJson('/api/v1/auth/me')->assertOk();
        $this->assertNotNull(User::query()->where('email', 'customer@example.com')->value('last_login_at'));
    }

    public function test_register_ignores_role_injection(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Injected Admin',
            'email' => 'injected@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'super_admin',
            'status' => 'disabled',
            'hotel_id' => 999,
        ])->assertCreated()->assertJsonPath('user.role', 'customer');

        $this->assertDatabaseHas('users', [
            'email' => 'injected@example.com',
            'role' => 'customer',
            'status' => 'active',
            'hotel_id' => null,
        ]);
    }

    public function test_debug_otp_can_reset_password_in_testing(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $oldToken = $user->createToken('old')->plainTextToken;

        $forgot = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
            ->assertOk()
            ->assertJsonStructure(['message', 'debug_otp']);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'otp' => $forgot->json('debug_otp'),
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertNotNull(PasswordResetOtp::query()->firstOrFail()->used_at);
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_wrong_otp_is_rejected_and_increments_attempts(): void
    {
        User::factory()->create(['email' => 'wrong-otp@example.com']);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'wrong-otp@example.com'])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'wrong-otp@example.com',
            'otp' => '000000',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable()->assertJsonPath('message', 'Mã khôi phục mật khẩu không chính xác hoặc đã hết hạn.');

        $this->assertSame(1, PasswordResetOtp::query()->firstOrFail()->attempts);
    }

    public function test_disabled_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => 'Password123!',
            'status' => 'disabled',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled@example.com',
            'password' => 'Password123!',
        ])->assertForbidden()->assertJsonPath('message', 'Tài khoản này đã bị vô hiệu hóa.');
    }
}
