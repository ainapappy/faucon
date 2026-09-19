<?php

use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('registration options require recent password confirmation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('passkey.registration-options'));

    $response->assertRedirect(route('password.confirm'));
});

test('registration options can be fetched after password confirmation', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->get(route('passkey.registration-options'));

    $response->assertOk()
        ->assertJsonStructure(['options']);

    expect(session('passkey.registration_options'))->not->toBeNull();
});

test('passkey registration rejects an invalid credential payload', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->post(route('passkey.store'), [
            'name' => 'iPhone',
            'credential' => ['type' => 'wrong-type'],
        ]);

    $response->assertSessionHasErrors('credential.type');
});

test('passkey registration rejects an expired registration session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->post(route('passkey.store'), [
            'name' => 'iPhone',
            'credential' => [
                'id' => Str::random(43),
                'rawId' => Str::random(45),
                'type' => 'public-key',
                'response' => ['attestationObject' => 'x'],
            ],
        ]);

    $response->assertSessionHasErrors('credential');
});

test('users can delete their own passkey', function () {
    $user = User::factory()->create();

    $passkey = $user->passkeys()->create([
        'name' => 'iPhone',
        'credential_id' => Str::random(43),
        'credential' => ['type' => 'public-key'],
    ]);

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->delete(route('passkey.destroy', $passkey));

    $response->assertRedirect();

    $this->assertModelMissing($passkey);
});

test("users can not delete another user's passkey", function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $passkey = $other->passkeys()->create([
        'name' => 'iPhone',
        'credential_id' => Str::random(43),
        'credential' => ['type' => 'public-key'],
    ]);

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->delete(route('passkey.destroy', $passkey));

    $response->assertForbidden();
    $this->assertModelExists($passkey);
});

test('passkey deletion requires recent password confirmation', function () {
    $user = User::factory()->create();

    $passkey = $user->passkeys()->create([
        'name' => 'iPhone',
        'credential_id' => Str::random(43),
        'credential' => ['type' => 'public-key'],
    ]);

    $response = $this->actingAs($user)->delete(route('passkey.destroy', $passkey));

    $response->assertRedirect(route('password.confirm'));
    $this->assertModelExists($passkey);
});

test('security page lists registered passkeys', function () {
    $user = User::factory()->create();

    $passkey = $user->passkeys()->create([
        'name' => 'iPhone',
        'credential_id' => Str::random(43),
        'credential' => ['type' => 'public-key'],
    ]);

    $passkey->forceFill(['last_used_at' => now()])->save();
    $passkey->refresh();

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManagePasskeys', true)
            ->has('passkeys', 1)
            ->where('passkeys.0.id', $passkey->id)
            ->where('passkeys.0.name', 'iPhone')
            ->where('passkeys.0.lastUsedAt', $passkey->last_used_at->toIso8601String()),
        );
});
