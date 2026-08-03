<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Marvel\Database\Models\SocialLoginCode;
use Marvel\Database\Models\User;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\SocialLoginExchangeRequest;
use Marvel\Http\Resources\UserResource;

class SocialController extends CoreController
{
    /**
     * Redirect the browser to the OAuth provider (Google / Facebook).
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)
            ->stateless()
            ->redirectUrl($this->callbackUrl($provider))
            ->redirect();
    }

    /**
     * Backwards-compatible alias: GET /api/v1/social/redirect?provider=google
     */
    public function redirectFromQuery(Request $request): RedirectResponse
    {
        return $this->redirect((string) $request->query('provider'));
    }

    /**
     * Handle the OAuth provider callback.
     *
     * On success it issues a single-use authorization code and redirects the
     * browser to the frontend. The API token is never placed in the URL.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->redirectUrl($this->callbackUrl($provider))
                ->user();

            $user = User::firstOrCreate(
                [
                    'email' => $socialUser->getEmail(),
                ],
                [
                    'email_verified_at' => now(),
                    'name' => $socialUser->getName(),
                    'password' => Hash::make(Str::random(32)),
                    'type' => 'user',
                ]
            );

            $user->providers()->updateOrCreate(
                [
                    'provider' => $provider,
                    'provider_user_id' => $socialUser->getId(),
                ]
            );

            $authorizationCode = SocialLoginCode::create([
                'user_id' => $user->id,
                'code' => bin2hex(random_bytes(32)),
                'expires_at' => now()->addMinutes((int) config('shop.social_login.code_ttl_minutes', 5)),
                'used' => false,
            ]);

            return redirect()->away($this->frontendUrl() . '/?code=' . $authorizationCode->code);
        } catch (\Throwable $e) {
            Log::error('Social login callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return redirect()->away($this->frontendUrl() . '/auth?error=social_login_failed');
        }
    }

    /**
     * Exchange a single-use authorization code for a Sanctum token.
     */
    public function exchange(SocialLoginExchangeRequest $request): JsonResponse
    {
        $claimed = SocialLoginCode::query()
            ->where('code', $request->validated('code'))
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->update(['used' => true, 'used_at' => now()]);

        if ($claimed !== 1) {
            return $this->invalidCodeResponse();
        }

        $code = SocialLoginCode::where('code', $request->validated('code'))->first();

        if (!$code) {
            return $this->invalidCodeResponse();
        }

        $code->delete();

        $user = User::where('id', $code->user_id)->where('is_active', true)->first();

        if (!$user) {
            return $this->invalidCodeResponse();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('message.MESSAGE.SOCIAL_LOGIN_SUCCESSFUL'),
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('roles')),
        ], 200);
    }

    protected function validateProvider(string $provider): void
    {
        if (!in_array($provider, ['facebook', 'google'], true)) {
            throw new MarvelException(PLEASE_LOGIN_USING_FACEBOOK_OR_GOOGLE);
        }
    }

    protected function callbackUrl(string $provider): string
    {
        return url("api/v1/social/{$provider}/callback");
    }

    protected function frontendUrl(): string
    {
        return rtrim((string) config('shop.social_login.frontend_url', ''), '/');
    }

    protected function invalidCodeResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('message.ERROR.INVALID_SOCIAL_LOGIN_CODE'),
        ], 400);
    }
}
