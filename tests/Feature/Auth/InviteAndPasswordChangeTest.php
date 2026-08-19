<?php

namespace Tests\Feature\Auth;

use App\Mail\SystemMarkdownMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteAndPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->twoFactorEnabled()->create([
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_admin_can_invite_a_user_and_email_is_sent(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin())->post(route('users.invite'), [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'role' => User::ROLE_EDITOR,
        ]);

        $response->assertRedirect(route('users.index'));

        $invited = User::where('email', 'nuevo@example.com')->first();
        $this->assertNotNull($invited);
        $this->assertTrue($invited->must_change_password);
        $this->assertFalse($invited->hasEnabledTwoFactor());

        Mail::assertSent(SystemMarkdownMail::class, fn ($mail) => $mail->hasTo('nuevo@example.com'));
    }

    public function test_invited_user_is_forced_to_change_password_first(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temporal123'),
            'must_change_password' => true,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'temporal123']);
        $this->assertAuthenticatedAs($user);

        // Antes que nada (incluso antes del 2FA) se le exige cambiar la contraseña.
        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('two-factor.setup'))->assertRedirect(route('password.change'));
    }

    public function test_changing_password_lifts_the_flag_and_then_forces_two_factor(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temporal123'),
            'must_change_password' => true,
        ]);
        $this->actingAs($user);

        $response = $this->post(route('password.change.store'), [
            'password' => 'MiNuevaClave456',
            'password_confirmation' => 'MiNuevaClave456',
        ]);
        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('MiNuevaClave456', $user->password));

        // Ahora el siguiente paso obligatorio es el 2FA.
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));
    }

    public function test_new_password_cannot_equal_the_temporary_one(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('temporal123'),
            'must_change_password' => true,
        ]);
        $this->actingAs($user);

        $response = $this->from(route('password.change'))->post(route('password.change.store'), [
            'password' => 'temporal123',
            'password_confirmation' => 'temporal123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->must_change_password);
    }
}
