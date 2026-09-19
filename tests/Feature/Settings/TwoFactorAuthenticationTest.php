<?php

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
});

test('users can enable two factor authentication', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.enable'));

    $response->assertRedirect();
    $response->assertSessionHas('status', Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED);

    expect($user->refresh()->two_factor_secret)->not->toBeNull();
    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

test('managing two factor authentication requires recent password confirmation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('two-factor.enable'));

    $response->assertRedirect(route('password.confirm'));
});

test('two factor authentication can be confirmed with a valid code', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt($this->secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
    ]);

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => app(Google2FA::class)->getCurrentOtp($this->secret)]);

    $response->assertRedirect();
    $response->assertSessionHas('status', Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED);

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('two factor authentication can not be confirmed with an invalid code', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt($this->secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
    ]);

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), ['code' => '000000']);

    $response->assertSessionHasErrors('code', null, 'confirmTwoFactorAuthentication');

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();
});

test('users can disable two factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'));

    $response->assertRedirect();
    $response->assertSessionHas('status', Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED);

    $user = $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

test('users can fetch the two factor qr code', function () {
    $user = User::factory()->create(['two_factor_secret' => encrypt($this->secret)]);

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.qr-code'));

    $response->assertOk()->assertJsonStructure(['svg', 'url']);
});

test('users can fetch the two factor secret key', function () {
    $user = User::factory()->create(['two_factor_secret' => encrypt($this->secret)]);

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.secret-key'));

    $response->assertOk()->assertJsonPath('secretKey', $this->secret);
});

test('users can fetch their recovery codes', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.recovery-codes'));

    $response->assertOk()->assertExactJson(['recovery-code-1']);
});

test('users can regenerate recovery codes', function () {
    $user = User::factory()->withTwoFactor()->create();

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.recovery-codes'));

    $response->assertRedirect();

    expect($user->refresh()->recoveryCodes())->not->toContain('recovery-code-1');
});
