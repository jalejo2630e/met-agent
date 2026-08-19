<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El 2FA es obligatorio: cualquier usuario autenticado que aún no lo haya
 * configurado es redirigido a la pantalla de enrolamiento antes de poder
 * acceder al resto de la aplicación.
 */
class EnsureTwoFactorEnrolled
{
    /**
     * Rutas permitidas mientras el usuario no ha terminado de configurar el 2FA.
     */
    private const ALLOWED_ROUTES = [
        'two-factor.setup',
        'two-factor.setup.store',
        'two-factor.recovery-codes',
        'password.change',
        'password.change.store',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasEnabledTwoFactor()) {
            $routeName = $request->route()?->getName();

            if (! in_array($routeName, self::ALLOWED_ROUTES, true)) {
                return redirect()->route('two-factor.setup');
            }
        }

        return $next($request);
    }
}
