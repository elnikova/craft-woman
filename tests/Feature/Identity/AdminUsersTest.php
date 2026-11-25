<?php

use App\Enums\Role;
use App\Models\User;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

uses()->group('admin-users');

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->buyer = User::factory()->create(['name' => 'Олена Покупниця', 'email' => 'olena@test.local', 'role' => Role::Buyer, 'password' => 'secret-123']);
});

test('only admins see the user list; search and filters work', function () {
    $this->actingAs($this->buyer)->getJson('/api/v1/admin/users')->assertForbidden();

    $this->actingAs($this->admin)->getJson('/api/v1/admin/users?q=олена')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'olena@test.local');
    $this->getJson('/api/v1/admin/users?role=admin')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_you', true);
});

test('blocking signs the user out and stops them signing in', function () {
    $this->actingAs($this->admin)->postJson("/api/v1/admin/users/{$this->buyer->uuid}/block", ['reason' => 'Шахрайські замовлення'])
        ->assertOk()->assertJsonPath('data.status', 'blocked')->assertJsonPath('data.blocked_reason', 'Шахрайські замовлення');

    // An existing session is cut off on the next request
    $this->actingAs($this->buyer->fresh())->getJson('/api/v1/me', ['Accept-Language' => 'uk'])
        ->assertForbidden()->assertJsonPath('code', 'account_blocked');

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/login', ['email' => 'olena@test.local', 'password' => 'secret-123'])
        ->assertStatus(422)->assertJsonValidationErrors('email');
    $this->assertGuest('web');
});

test('unblocking restores access', function () {
    $this->actingAs($this->admin)->postJson("/api/v1/admin/users/{$this->buyer->uuid}/block", ['reason' => 'Перевірка']);
    $this->postJson("/api/v1/admin/users/{$this->buyer->uuid}/unblock")->assertOk()->assertJsonPath('data.status', 'active');
    $this->postJson("/api/v1/admin/users/{$this->buyer->uuid}/unblock")->assertStatus(409);

    $this->app['auth']->forgetGuards();
    $this->postJson('/api/v1/login', ['email' => 'olena@test.local', 'password' => 'secret-123'])->assertOk();
});

test('admins cannot act on themselves, and the last admin stays', function () {
    $this->actingAs($this->admin, 'web');
    $this->postJson("/api/v1/admin/users/{$this->admin->uuid}/block", ['reason' => 'Тест себе'])->assertStatus(409);
    $this->patchJson("/api/v1/admin/users/{$this->admin->uuid}/role", ['role' => 'buyer'], ['Accept-Language' => 'uk'])
        ->assertStatus(409)->assertJsonPath('message', 'Свій акаунт не можна заблокувати чи змінити собі роль.');

    // Promote, then the second admin can't demote the first if that leaves none active
    $this->patchJson("/api/v1/admin/users/{$this->buyer->uuid}/role", ['role' => 'admin'])->assertOk()->assertJsonPath('data.role', 'admin');
    $this->actingAs($this->buyer->fresh())->patchJson("/api/v1/admin/users/{$this->admin->uuid}/role", ['role' => 'buyer'])->assertOk();
    $this->admin->refresh();
    expect($this->admin->role)->toBe(Role::Buyer);
});

test('the last active admin cannot be blocked or demoted', function () {
    $second = User::factory()->create(['role' => Role::Admin]);
    $second->forceFill(['status' => 'blocked'])->save(); // blocked admins don't count

    $acting = User::factory()->create(['role' => Role::Admin]);
    // $acting and $this->admin are active; blocking $this->admin leaves $acting, so it's allowed
    $this->actingAs($acting)->postJson("/api/v1/admin/users/{$this->admin->uuid}/block", ['reason' => 'Тестування'])->assertOk();

    // Now $acting is the only active admin: a demoted copy of the rule protects it
    $acting->forceFill(['role' => Role::Buyer])->saveQuietly();
    $this->app['auth']->forgetGuards();
    $this->admin->forceFill(['status' => 'active'])->save();
    $acting->forceFill(['role' => Role::Admin])->save();
    $this->admin->forceFill(['role' => Role::Buyer])->save();

    $judge = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($judge)->patchJson("/api/v1/admin/users/{$acting->uuid}/role", ['role' => 'buyer'])->assertOk();
    $this->actingAs($judge)->postJson("/api/v1/admin/users/{$judge->uuid}/block", ['reason' => 'Сам себе'])->assertStatus(409);

    // $judge is the last active admin; another admin-less attempt is impossible, so check the service directly
    expect(fn () => app(\Modules\Identity\Application\Services\UserAdminService::class)->block($this->buyer, $judge, 'Останній адмін'))
        ->toThrow(\Modules\Identity\Domain\Exceptions\AccountRuleViolation::class);
});

test('maker accounts keep their role', function () {
    $maker = MakerProfile::factory()->approved()->create();
    $this->actingAs($this->admin)->patchJson("/api/v1/admin/users/{$maker->user_uuid}/role", ['role' => 'admin'])->assertStatus(409);
    $this->patchJson("/api/v1/admin/users/{$this->buyer->uuid}/role", ['role' => 'master'])->assertStatus(422);
});
