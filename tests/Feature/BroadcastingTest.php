<?php

use App\Models\User;

beforeEach(function () {
    // Le broadcaster « null » de l'environnement de test autorise toute
    // souscription : bascule sur le driver reverb et réenregistre les
    // canaux de l'application pour exercer le vrai pipeline d'autorisation
    // (signature locale, aucun appel réseau).
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
});

test('authorizes a user subscribing to their own private user channel', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-App.Models.User.{$user->id}",
        ]);

    $response->assertOk();
});

test('denies a user subscribing to another user private channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-App.Models.User.{$other->id}",
        ]);

    $response->assertForbidden();
});

test('denies guests subscribing to private channels', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-App.Models.User.{$user->id}",
    ]);

    $response->assertForbidden();
});
