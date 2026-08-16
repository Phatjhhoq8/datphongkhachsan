<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const MAX_OTP_ATTEMPTS = 5;

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'role' => 'customer',
            'status' => 'active',
        ]);

        return response()->json($this->tokenResponse($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower($validated['email']))->first();

        if (! $user || ! is_string($user->password) || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Thông tin đăng nhập không chính xác.'], 422);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Tài khoản này đã bị vô hiệu hóa.'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json($this->tokenResponse($user->fresh()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->update($validated);

        return response()->json(['data' => $request->user()->fresh()]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! is_string($request->user()->password) || ! Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không chính xác.',
                'errors' => ['current_password' => ['Mật khẩu hiện tại không chính xác.']],
            ], 422);
        }

        $request->user()->update(['password' => $validated['password']]);
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Đổi mật khẩu thành công.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $message = 'Nếu email tồn tại trên hệ thống, mã khôi phục mật khẩu đã được gửi đi.';
        $email = is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : '';

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! User::query()->where('email', $email)->exists()) {
            return response()->json(['message' => $message]);
        }

        $otp = (string) random_int(100000, 999999);

        PasswordResetOtp::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        PasswordResetOtp::query()->create([
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
        ]);

        try {
            Mail::raw("Your StayGo password reset code is {$otp}. It expires in 5 minutes.", function ($mail) use ($email) {
                $mail->to($email)->subject('StayGo password reset code');
            });
        } catch (\Throwable $exception) {
            report($exception);
        }

        $response = ['message' => $message];

        if (app()->environment(['local', 'testing'])) {
            $response['debug_otp'] = $otp;
        }

        return response()->json($response);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $email = strtolower($validated['email']);

        $reset = DB::transaction(function () use ($email, $validated): bool {
            $otp = PasswordResetOtp::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->where('attempts', '<', self::MAX_OTP_ATTEMPTS)
                ->latest('created_at')
                ->first();

            if (! $otp || ! Hash::check($validated['otp'], $otp->otp_hash)) {
                $otp?->increment('attempts');

                return false;
            }

            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                return false;
            }

            $user->update(['password' => $validated['password']]);
            $user->tokens()->delete();
            $otp->update(['used_at' => now()]);

            return true;
        });

        if (! $reset) {
            return response()->json(['message' => 'Mã khôi phục mật khẩu không chính xác hoặc đã hết hạn.'], 422);
        }

        return response()->json(['message' => 'Khôi phục mật khẩu thành công.']);
    }

    /** @return array{token: string, token_type: string, user: User} */
    private function tokenResponse(User $user): array
    {
        return [
            'token' => $user->createToken('frontend')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }
}
