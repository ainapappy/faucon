<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
});

test('two factor challenge screen can be rendered for the challenged user', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->session(['login.id' => $user->id]);

    $response = $this->get(route('two-factor.login'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));
});

test('two factor challenge redirects to login without a challenged session', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('users can authenticate with a valid two factor code', function () {
    $user = User::factory()->withTwoFactor()->create(['two_factor_secret' => encrypt($this->secret)]);

    $this->session(['login.id' => $user->id]);

    $response = $this->post(route('two-factor.login.store'), [
        'code' => app(Google2FA::class)->getCurrentOtp($this->secret),
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});

test('users can not authenticate with an invalid two factor code', function () {
    $user = User::factory()->withTwoFactor()->create(['two_factor_secret' => encrypt($this->secret)]);

    $this->session(['login.id' => $user->id]);

    $response = $this->post(route('two-factor.login.store'), [
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('users can authenticate with a recovery code', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->session(['login.id' => $user->id]);

    $response = $this->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});

test('used recovery codes are replaced', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->session(['login.id' => $user->id]);

    $this->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ]);

    $user->refresh();

    expect($user->recoveryCodes())->not->toContain('recovery-code-1');
});
