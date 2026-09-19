<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
});

test('new users can register and are redirected to email verification', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ]);

    $response->assertRedirect(route('verification.notice'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);
});

test('registration requires valid information', function () {
    $response = $this->post(route('register.store'), [
        'name' => '',
        'email' => '',
        'password' => 'password',
        'password_confirmation' => 'not-the-same',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
    $this->assertGuest();
});

test('registration fails when terms are not accepted', function () {
    $response = $this->postJson(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertUnprocessable()
        ->assertInvalid(['terms' => 'The terms field must be accepted.']);
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('registration lowercases the email address', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'MiXeD@ExAmPlE.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ]);

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'mixed@example.com']);
});

test('new users receive an email verification notification', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => true,
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('registration screen redirects authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('register'));

    $response->assertRedirect(route('dashboard'));
});
