<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses()->group('account');

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Дарія', 'email' => 'dasha@test.local', 'password' => 'old-password', 'role' => Role::Buyer]);
});

test('user renames themselves without a password', function () {
    $this->actingAs($this->user)->patchJson('/api/v1/me/account', ['name' => 'Дарія К.', 'email' => 'dasha@test.local'])
        ->assertOk()->assertJsonPath('data.name', 'Дарія К.');
});

test('changing e-mail needs the current password and resets verification', function () {
    $this->user->forceFill(['email_verified_at' => now()])->save();
    $this->actingAs($this->user);

    $this->patchJson('/api/v1/me/account', ['name' => 'Дарія', 'email' => 'new@test.local'])
        ->assertStatus(422)->assertJsonValidationErrors('current_password');
    $this->patchJson('/api/v1/me/account', ['name' => 'Дарія', 'email' => 'new@test.local', 'current_password' => 'wrong'], ['Accept-Language' => 'uk'])
        ->assertJsonValidationErrors(['current_password' => 'Неправильний поточний пароль.']);

    $this->patchJson('/api/v1/me/account', ['name' => 'Дарія', 'email' => 'new@test.local', 'current_password' => 'old-password'])
        ->assertOk()->assertJsonPath('data.email', 'new@test.local')->assertJsonPath('data.email_verified', false);
});

test('e-mail must stay unique', function () {
    User::factory()->create(['email' => 'taken@test.local']);
    $this->actingAs($this->user)->patchJson('/api/v1/me/account', ['name' => 'Дарія', 'email' => 'taken@test.local', 'current_password' => 'old-password'])
        ->assertJsonValidationErrors('email');
});

test('password change checks the old password and signs out other sessions', function () {
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $this->user->uuid, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs($this->user);

    $this->putJson('/api/v1/me/password', ['current_password' => 'nope', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertJsonValidationErrors('current_password');
    $this->putJson('/api/v1/me/password', ['current_password' => 'old-password', 'password' => 'old-password', 'password_confirmation' => 'old-password'])
        ->assertJsonValidationErrors('password');

    $this->putJson('/api/v1/me/password', ['current_password' => 'old-password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertOk();

    expect(Hash::check('new-password-1', $this->user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
});
