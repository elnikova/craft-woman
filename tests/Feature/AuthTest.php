<?php

use App\Enums\Role;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

    $response->assertCreated()
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

    $this->assertGuest('web');
});

test('register assigns buyer role by default', function () {
    $this->postJson('/api/register', [
        'name' => 'Buyer',
        'email' => 'buyer@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()->assertJsonFragment(['role' => 'buyer']);

    expect(User::where('email', 'buyer@example.com')->value('role'))->toBe(Role::Buyer);
});

test('register can create a master account', function () {
    $this->postJson('/api/register', [
        'name' => 'Maker',
        'email' => 'maker@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'master',
    ])->assertCreated()->assertJsonFragment(['role' => 'master']);

    expect(User::where('email', 'maker@example.com')->value('role'))->toBe(Role::Master);
});

test('register does not allow choosing the admin role', function () {
    $this->postJson('/api/register', [
        'name' => 'Sneaky',
        'email' => 'sneaky@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'admin',
    ])->assertStatus(422)->assertJsonValidationErrors('role');

    expect(User::where('email', 'sneaky@example.com')->exists())->toBeFalse();
});

test('login authenticates the user in the session', function () {
    User::factory()->create([
        'email' => 'session@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/login', [
        'email' => 'session@example.com',
        'password' => 'secret123',
    ])->assertOk();

    $this->assertAuthenticated();
});
