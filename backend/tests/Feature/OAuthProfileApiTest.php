<?php

namespace Tests\Feature;

use App\Models\OAuthExchangeCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\Concerns\RefreshMongoDatabase;
use Tests\TestCase;

class OAuthProfileApiTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'http://localhost:5173',
            'services.google.client_id' => 'test-google-id',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
            'services.facebook.client_id' => null,
            'services.facebook.client_secret' => null,
            'services.facebook.redirect' => null,
        ]);
    }

    public function test_capabilities_only_report_fully_configured_providers(): void
    {
        $this->getJson('/api/v1/auth/oauth/providers')
            ->assertOk()
            ->assertExactJson(['data' => ['google' => true, 'facebook' => false]]);
    }

    public function test_unknown_and_unconfigured_providers_are_rejected(): void
    {
        $this->getJson('/api/v1/auth/oauth/github/redirect')->assertNotFound();
        $this->getJson('/api/v1/auth/oauth/facebook/redirect')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'The OAuth provider is not configured.');
    }

    public function test_verified_provider_email_creates_user_and_callback_only_exposes_exchange_code(): void
    {
        $this->mockSocialiteUser('google', $this->socialiteUser('google-123', 'oauth@example.com', true));

        $response = $this->get('/api/v1/auth/oauth/google/callback')->assertRedirect();
        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('http://localhost:5173/auth/oauth/callback?', $location);
        $this->assertArrayHasKey('code', $query);
        $this->assertArrayNotHasKey('token', $query);
        $this->assertDatabaseHas('users', [
            'email' => 'oauth@example.com',
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);
        $this->assertDatabaseMissing('oauth_exchange_codes', ['code_hash' => $query['code']]);

        $exchange = $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $query['code']])
            ->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.email', 'oauth@example.com');

        $this->withToken($exchange->json('token'))->getJson('/api/v1/auth/me')->assertOk();
        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $query['code']])
            ->assertUnprocessable();
    }

    public function test_verified_email_links_existing_user_but_unverified_email_cannot_claim_it(): void
    {
        $existing = User::factory()->create(['email' => 'owner@example.com']);
        $this->mockSocialiteUser('google', $this->socialiteUser('verified-id', 'owner@example.com', true));
        $this->get('/api/v1/auth/oauth/google/callback')->assertRedirect();

        $this->assertSame((string) $existing->id, (string) User::query()->where('provider_id', 'verified-id')->firstOrFail()->id);
        $this->assertDatabaseCount('users', 1);

        $this->mockSocialiteUser('google', $this->socialiteUser('attacker-id', 'owner@example.com', false));
        $this->get('/api/v1/auth/oauth/google/callback')->assertRedirectContains('error=unverified_email');
        $this->assertDatabaseMissing('users', ['provider_id' => 'attacker-id']);
    }

    public function test_expired_exchange_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $plainCode = str_repeat('x', 64);
        OAuthExchangeCode::query()->create([
            'code_hash' => hash('sha256', $plainCode),
            'user_id' => (string) $user->id,
            'provider' => 'google',
            'expires_at' => now()->subSecond(),
        ]);

        $this->postJson('/api/v1/auth/oauth/exchange', ['code' => $plainCode])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The exchange code is invalid or has expired.');
    }

    public function test_profile_update_is_owned_by_authenticated_user(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $other = User::factory()->create(['name' => 'Other']);

        $this->actingAs($owner)->patchJson('/api/v1/auth/profile', [
            'name' => 'Updated Owner',
            'phone' => '0901234567',
            'user_id' => (string) $other->id,
            'email' => 'stolen@example.com',
            'role' => 'super_admin',
        ])->assertOk()->assertJsonPath('data.name', 'Updated Owner');

        $this->assertSame('Updated Owner', $owner->fresh()->name);
        $this->assertSame('Other', $other->fresh()->name);
        $this->assertNotSame('super_admin', $owner->fresh()->role);
    }

    public function test_password_change_requires_current_password_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123!']);
        $currentToken = $user->createToken('current')->plainTextToken;
        $user->createToken('other');

        $this->withToken($currentToken)->putJson('/api/v1/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable();

        $this->withToken($currentToken)->putJson('/api/v1/auth/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function mockSocialiteUser(string $providerName, SocialiteUser $user): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($user);
        Socialite::shouldReceive('driver')->with($providerName)->once()->andReturn($provider);
    }

    private function socialiteUser(string $id, string $email, bool $verified): SocialiteUser
    {
        return (new SocialiteUser)->setRaw(['email_verified' => $verified, 'verified' => $verified])->map([
            'id' => $id,
            'name' => 'OAuth Customer',
            'email' => $email,
            'avatar' => 'https://example.com/avatar.jpg',
        ]);
    }
}
