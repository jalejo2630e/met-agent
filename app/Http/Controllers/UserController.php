<?php

namespace App\Http\Controllers;

use App\Mail\SystemMarkdownMail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Listar usuarios del sistema (solo administrador).
     */
    public function index()
    {
        $users = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'email_verified_at' => $u->email_verified_at?->toISOString(),
                'two_factor_enabled' => $u->hasEnabledTwoFactor(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Crear un usuario (solo administrador).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_EDITOR])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
    }

    /**
     * Invitar a un usuario: crea la cuenta con una contraseña temporal y le
     * envía por correo sus accesos (solo administrador).
     */
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_EDITOR])],
        ]);

        $temporaryPassword = Str::password(12, symbols: false);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        $companyName = Setting::get('company_name') ?: config('app.name');

        try {
            Mail::to($user->email)->send(new SystemMarkdownMail(
                subjectLine: 'Tu acceso a '.$companyName,
                heading: 'Te damos la bienvenida a '.$companyName,
                bodyLines: [
                    'Hola '.$user->name.', se creó una cuenta para que accedas a la plataforma.',
                    'Estos son tus datos de acceso:',
                    'Correo: '.$user->email,
                    'Contraseña temporal: '.$temporaryPassword,
                    'Por seguridad, en tu primer ingreso deberás cambiar esta contraseña temporal y configurar la verificación en dos pasos con Google Authenticator.',
                ],
                actionUrl: route('login'),
                actionText: 'Iniciar sesión',
            ));
        } catch (\Throwable $e) {
            // Si el correo no se pudo enviar, no dejamos la cuenta huérfana.
            $user->delete();
            report($e);

            return redirect()->route('users.index')
                ->with('error', 'No se pudo enviar la invitación. Revisa la configuración de correo e inténtalo de nuevo.');
        }

        return redirect()->route('users.index')
            ->with('success', 'Invitación enviada a '.$user->email.' con sus datos de acceso.');
    }

    /**
     * Actualizar un usuario (solo administrador).
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_EDITOR])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Eliminar un usuario (solo administrador). No se puede eliminar a uno mismo.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Restablecer la verificación en dos pasos de un usuario (solo administrador).
     * Úsalo cuando un usuario pierde su app de autenticación y sus códigos de
     * recuperación; deberá configurarla de nuevo en su próximo inicio de sesión.
     */
    public function resetTwoFactor(User $user)
    {
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return redirect()->route('users.index')
            ->with('success', 'Se restableció la verificación en dos pasos de '.$user->name.'. Deberá configurarla de nuevo al iniciar sesión.');
    }
}
