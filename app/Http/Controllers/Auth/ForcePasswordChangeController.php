<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    /**
     * Pantalla para que el usuario invitado establezca su propia contraseña.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ForcePasswordChange');
    }

    /**
     * Guarda la nueva contraseña y levanta la marca de cambio obligatorio.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // La nueva contraseña no puede ser igual a la temporal recibida por correo.
        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors([
                'password' => __('La nueva contraseña debe ser distinta a la temporal.'),
            ]);
        }

        $user->password = Hash::make($request->input('password'));
        $user->must_change_password = false;
        $user->save();

        return redirect()->route('dashboard')
            ->with('success', 'Tu contraseña se actualizó correctamente.');
    }
}
