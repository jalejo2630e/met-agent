<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAuthenticatedSessionController extends Controller
{
    public function __construct(private TwoFactorAuthenticator $authenticator) {}

    /**
     * Muestra el reto OTP para usuarios que ya tienen 2FA configurado.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verifica el código TOTP (o un código de recuperación) y completa el login.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $this->ensureIsNotRateLimited($request);

        $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $recoveryCode = trim((string) $request->input('recovery_code'));
        $code = trim((string) $request->input('code'));

        if ($recoveryCode !== '') {
            $this->verifyRecoveryCode($request, $user, $recoveryCode);
        } elseif ($code !== '') {
            if (! $this->authenticator->verify($user->two_factor_secret, $code)) {
                RateLimiter::hit($this->throttleKey($request));
                throw ValidationException::withMessages([
                    'code' => __('El código de verificación es incorrecto.'),
                ]);
            }
        } else {
            throw ValidationException::withMessages([
                'code' => __('Ingresa el código de verificación.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $remember = (bool) $request->session()->get('login.remember', false);
        Auth::login($user, $remember);

        $request->session()->forget(['login.id', 'login.remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Valida y consume un código de recuperación de un solo uso.
     */
    private function verifyRecoveryCode(Request $request, User $user, string $recoveryCode): void
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        $match = collect($codes)->first(fn ($code) => hash_equals($code, $recoveryCode));

        if (! $match) {
            RateLimiter::hit($this->throttleKey($request));
            throw ValidationException::withMessages([
                'recovery_code' => __('El código de recuperación no es válido.'),
            ]);
        }

        // Se consume: los códigos de recuperación son de un solo uso.
        $user->two_factor_recovery_codes = array_values(
            array_filter($codes, fn ($code) => ! hash_equals($code, $match))
        );
        $user->save();
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('login.id');

        if (! $userId) {
            return null;
        }

        $user = User::find($userId);

        if (! $user || ! $user->hasEnabledTwoFactor()) {
            $request->session()->forget(['login.id', 'login.remember']);

            return null;
        }

        return $user;
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::lower('2fa|'.$request->session()->get('login.id').'|'.$request->ip());
    }
}
