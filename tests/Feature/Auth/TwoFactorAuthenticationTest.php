<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledUser(): array
    {
        $secret = (new TwoFactorAuthenticator())->generateSecretKey();

        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['ABCDE-FGHIJ', 'KLMNO-PQRST'],
            'two_factor_confirmed_at' => now(),
        ]);

        return [$user, $secret];
    }

    public function test_enrolled_user_is_challenged_instead_of_logged_in(): void
    {
        [$user] = $this->enrolledUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_valid_otp_completes_login(): void
    {
        [$user, $secret] = $this->enrolledUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $code = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->post('/two-factor-challenge', ['code' => $code]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_otp_is_rejected(): void
    {
        [$user] = $this->enrolledUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post('/two-factor-challenge', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_completes_login_and_is_consumed(): void
    {
        [$user] = $this->enrolledUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post('/two-factor-challenge', ['recovery_code' => 'ABCDE-FGHIJ']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->assertNotContains('ABCDE-FGHIJ', $user->fresh()->two_factor_recovery_codes);
    }

    public function test_user_without_two_factor_is_forced_to_setup(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);

        // Cualquier ruta protegida redirige al enrolamiento obligatorio.
        $this->get(route('dashboard'))->assertRedirect(route('two-factor.setup'));
    }

    public function test_setup_enables_two_factor_and_generates_recovery_codes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Genera el secreto candidato en sesión.
        $this->get(route('two-factor.setup'))->assertOk();
        $secret = session('two_factor.candidate_secret');
        $this->assertNotEmpty($secret);

        $code = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->post(route('two-factor.setup.store'), ['code' => $code]);
        $response->assertRedirect(route('two-factor.recovery-codes'));

        $user->refresh();
        $this->assertTrue($user->hasEnabledTwoFactor());
        $this->assertCount(8, $user->two_factor_recovery_codes);
    }
}
