<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los usuarios invitados reciben una contraseña temporal por correo y deben
 * cambiarla en su primer ingreso antes de poder usar la plataforma.
 */
class EnsurePasswordIsChanged
{
    /**
     * Rutas permitidas mientras el usuario no ha cambiado su contraseña temporal.
     */
    private const ALLOWED_ROUTES = [
        'password.change',
        'password.change.store',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            $routeName = $request->route()?->getName();

            if (! in_array($routeName, self::ALLOWED_ROUTES, true)) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
