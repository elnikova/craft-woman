<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Middleware\VerifyCsrfToken;

uses()->group('auth');

test('register creates user and returns resource', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $payload = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->postJson('/api/register', $payload);

    $response->assertOk()
        ->assertJsonFragment([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

test('login returns user resource for valid credentials', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'login@example.com',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $user->id,
            'email' => 'login@example.com',
        ]);
});

test('login rejects invalid credentials', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    User::factory()->create([
        'email' => 'bad@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'bad@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422);
});

test('me returns current user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/me');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $user->id,
            'email' => $user->email,
        ]);
});

test('logout clears session', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->postJson('/api/logout');

    $response->assertOk()
        ->assertJsonFragment([
            'message' => 'Logged out.',
        ]);
});
