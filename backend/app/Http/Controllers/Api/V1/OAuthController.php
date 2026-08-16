<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OAuthExchangeCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OAuthController extends Controller
{
    private const PROVIDERS = ['google', 'facebook'];

    public function providers(): JsonResponse
    {
        return response()->json(['data' => collect(self::PROVIDERS)
            ->mapWithKeys(fn (string $provider) => [$provider => $this->isConfigured($provider)])
            ->all()]);
    }

    public function redirect(string $provider): RedirectResponse|JsonResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }

        if (! $this->isConfigured($provider)) {
            return response()->json(['message' => 'The OAuth provider is not configured.'], 503);
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            abort(404);
        }

        if (! $this->isConfigured($provider)) {
            return $this->frontendRedirect(['error' => 'provider_unavailable']);
        }

        try {
            $providerUser = Socialite::driver($provider)->stateless()->user();

            if (! $this->hasVerifiedEmail($providerUser)) {
                return $this->frontendRedirect(['error' => 'unverified_email']);
            }

            $email = strtolower(trim((string) $providerUser->getEmail()));
            $user = User::query()
                ->where('provider', $provider)
                ->where('provider_id', (string) $providerUser->getId())
                ->first() ?? User::query()->where('email', $email)->first();

            if ($user && $user->status !== 'active') {
                return $this->frontendRedirect(['error' => 'account_disabled']);
            }

            $attributes = [
                'name' => $providerUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'provider' => $provider,
                'provider_id' => (string) $providerUser->getId(),
                'avatar' => $providerUser->getAvatar(),
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ];

            if ($user) {
                $user->forceFill($attributes)->save();
            } else {
                $user = User::query()->create($attributes + [
                    'password' => Str::random(64),
                    'role' => 'customer',
                    'status' => 'active',
                ]);
            }

            $plainCode = Str::random(64);
            OAuthExchangeCode::query()->create([
                'code_hash' => hash('sha256', $plainCode),
                'user_id' => $user->id,
                'provider' => $provider,
                'expires_at' => now()->addMinutes(5),
            ]);

            return $this->frontendRedirect(['code' => $plainCode]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->frontendRedirect(['error' => 'oauth_failed']);
        }
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:64']]);
        $codeHash = hash('sha256', $validated['code']);
        $exchange = OAuthExchangeCode::query()
            ->where('code_hash', $codeHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $exchange) {
            return response()->json(['message' => 'The exchange code is invalid or has expired.'], 422);
        }

        $consumed = OAuthExchangeCode::query()
            ->where('id', $exchange->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $user = $consumed === 1 ? User::query()->find($exchange->user_id) : null;

        if (! $user || $user->status !== 'active') {
            return response()->json(['message' => 'The exchange code is invalid or has expired.'], 422);
        }

        return response()->json([
            'token' => $user->createToken('frontend')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    private function isConfigured(string $provider): bool
    {
        return collect(['client_id', 'client_secret', 'redirect'])
            ->every(fn (string $key) => filled(config("services.{$provider}.{$key}")));
    }

    private function hasVerifiedEmail(SocialiteUser $user): bool
    {
        $email = $user->getEmail();
        $raw = $user->getRaw();

        return is_string($email)
            && filter_var($email, FILTER_VALIDATE_EMAIL)
            && (($raw['email_verified'] ?? false) === true || ($raw['verified'] ?? false) === true);
    }

    /** @param array<string, string> $query */
    private function frontendRedirect(array $query): RedirectResponse
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/auth/oauth/callback?'.http_build_query($query);

        return redirect()->away($url);
    }
}
