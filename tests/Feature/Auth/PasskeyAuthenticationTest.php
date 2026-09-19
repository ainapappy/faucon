<?php

use App\Models\User;
use Illuminate\Support\Str;

test('passkey login options can be fetched by guests', function () {
    $response = $this->get(route('passkey.login-options'));

    $response->assertOk()
        ->assertJsonStructure(['options']);

    expect(session('passkey.verification_options'))->not->toBeNull();
});

test('passkey login options are not available to authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('passkey.login-options'));

    $response->assertRedirect(route('dashboard'));
});

test('passkey login rejects an invalid credential payload', function () {
    $response = $this->post(route('passkey.login'), [
        'credential' => ['type' => 'wrong-type'],
    ]);

    $response->assertSessionHasErrors('credential.type');
});

test('passkey login rejects when verification options are missing', function () {
    $response = $this->post(route('passkey.login'), [
        'credential' => [
            'id' => Str::random(43),
            'rawId' => Str::random(43),
            'type' => 'public-key',
            'response' => ['clientDataJSON' => 'x'],
        ],
    ]);

    $response->assertSessionHasErrors('credential');
});
