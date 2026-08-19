<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorSetupController extends Controller
{
    public function __construct(private TwoFactorAuthenticator $authenticator) {}

    /**
     * Pantalla de enrolamiento: muestra el QR y la clave secreta.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Si ya lo tiene configurado, no hay nada que hacer aquí.
        if ($user->hasEnabledTwoFactor()) {
            return redirect()->route('dashboard');
        }

        // Guardamos un secreto candidato en la sesión para que el QR sea estable
        // aunque el usuario recargue la página (aún no se persiste en la BD).
        $secret = $request->session()->get('two_factor.candidate_secret');
        if (! $secret) {
            $secret = $this->authenticator->generateSecretKey();
            $request->session()->put('two_factor.candidate_secret', $secret);
        }

        return Inertia::render('Auth/TwoFactorSetup', [
            'qrCode' => $this->authenticator->qrCodeDataUri($user, $secret),
            'secret' => $secret,
        ]);
    }

    /**
     * Confirma el código, activa el 2FA y genera los códigos de recuperación.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $secret = $request->session()->get('two_factor.candidate_secret');

        if (! $secret || ! $this->authenticator->verify($secret, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => __('El código es incorrecto. Verifica que escaneaste el QR y que la hora de tu teléfono es correcta.'),
            ]);
        }

        $recoveryCodes = $this->authenticator->generateRecoveryCodes();

        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->two_factor_confirmed_at = now();
        $user->save();

        $request->session()->forget('two_factor.candidate_secret');

        return redirect()->route('two-factor.recovery-codes')
            ->with('recoveryCodes', $recoveryCodes);
    }

    /**
     * Muestra (una sola vez) los códigos de recuperación recién generados.
     */
    public function recoveryCodes(Request $request): Response|RedirectResponse
    {
        $codes = $request->session()->get('recoveryCodes');

        // Solo accesible justo después de generar los códigos (flash de sesión).
        if (! $codes) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/TwoFactorRecoveryCodes', [
            'recoveryCodes' => $codes,
        ]);
    }
}
