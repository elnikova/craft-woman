<?php

use App\Enums\Role;
use App\Models\User;
use Modules\Identity\Domain\MakerStatus;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

uses()->group('makers');

function admin(): User
{
    return User::factory()->create(['role' => Role::Admin]);
}

function maker(): User
{
    return User::factory()->create(['role' => Role::Master]);
}

// ---------- Public directory ----------

test('public list shows only approved makers', function () {
    $approved = MakerProfile::factory()->approved()->create();
    MakerProfile::factory()->create(); // draft
    MakerProfile::factory()->pendingReview()->create();

    $this->getJson('/api/v1/makers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $approved->uuid);
});

test('public profile hides private fields', function () {
    $profile = MakerProfile::factory()->approved()->create(['address' => 'вул. Гончарна, 1']);

    $this->getJson("/api/v1/makers/{$profile->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $profile->slug)
        ->assertJsonMissingPath('data.address')
        ->assertJsonMissingPath('data.status');
});

test('unapproved profile is 404 for the public but visible to its owner', function () {
    $profile = MakerProfile::factory()->pendingReview()->create();

    $this->getJson("/api/v1/makers/{$profile->slug}")->assertNotFound();

    $this->actingAs($profile->user)
        ->getJson("/api/v1/makers/{$profile->slug}")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_review');
});

// ---------- Registration ----------

test('registering as a maker creates a draft profile', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Оксана Ткачук',
        'email' => 'oksana@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => 'master',
    ])->assertCreated()->assertJsonPath('data.maker_status', 'draft');

    $profile = User::where('email', 'oksana@example.com')->first()->makerProfile;
    expect($profile->status)->toBe(MakerStatus::Draft)
        ->and($profile->display_name)->toBe('Оксана Ткачук')
        ->and($profile->slug)->toBe('oksana-tkacuk');
});

test('registering as a buyer creates no maker profile', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Buyer',
        'email' => 'b@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()->assertJsonPath('data.maker_status', null);

    expect(MakerProfile::count())->toBe(0);
});

// ---------- Own profile (studio) ----------

test('own profile endpoint returns null when there is none', function () {
    $this->actingAs(maker())->getJson('/api/v1/me/maker-profile')
        ->assertOk()->assertExactJson(['data' => null]);
});

test('user can create and then update their own profile', function () {
    $user = maker();

    $this->actingAs($user)->putJson('/api/v1/me/maker-profile', [
        'display_name' => 'Майстерня Оксани',
        'city' => 'Львів',
        'country_code' => 'ua',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.country_code', 'UA');

    $this->actingAs($user)->putJson('/api/v1/me/maker-profile', [
        'display_name' => 'Майстерня Оксани',
        'bio' => 'В’яжу пледи з карпатської вовни',
    ])->assertOk()->assertJsonPath('data.bio', 'В’яжу пледи з карпатської вовни');

    expect(MakerProfile::where('user_uuid', $user->uuid)->count())->toBe(1);
});

test('profile validation errors are returned as 422', function () {
    $this->actingAs(maker())->putJson('/api/v1/me/maker-profile', [
        'display_name' => '',
        'country_code' => 'UKR',
        'avatar_url' => 'not-a-url',
    ])->assertStatus(422)->assertJsonValidationErrors(['display_name', 'country_code', 'avatar_url']);
});

test('submitting moves draft to pending review and locks editing', function () {
    $profile = MakerProfile::factory()->create();

    $this->actingAs($profile->user)->postJson('/api/v1/me/maker-profile/submit')
        ->assertOk()->assertJsonPath('data.status', 'pending_review');

    $this->actingAs($profile->user)->putJson('/api/v1/me/maker-profile', ['display_name' => 'New'])
        ->assertStatus(409);
});

test('submitting twice is a conflict', function () {
    $profile = MakerProfile::factory()->pendingReview()->create();

    $this->actingAs($profile->user)->postJson('/api/v1/me/maker-profile/submit')->assertStatus(409);
});

test('slug of an approved profile does not change on rename', function () {
    $profile = MakerProfile::factory()->approved()->create();
    $slug = $profile->slug;

    $this->actingAs($profile->user)->putJson('/api/v1/me/maker-profile', ['display_name' => 'Зовсім інша назва'])
        ->assertOk()->assertJsonPath('data.slug', $slug);
});

test('guests cannot access the studio endpoints', function () {
    $this->getJson('/api/v1/me/maker-profile')->assertUnauthorized();
    $this->putJson('/api/v1/me/maker-profile', ['display_name' => 'X'])->assertUnauthorized();
});

// ---------- Admin review ----------

test('admin sees the review queue', function () {
    MakerProfile::factory()->pendingReview()->count(2)->create();
    MakerProfile::factory()->approved()->create();

    $this->actingAs(admin())->getJson('/api/v1/admin/makers')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['owner' => ['email']]]]);
});

test('non-admins cannot review makers', function () {
    $profile = MakerProfile::factory()->pendingReview()->create();

    $this->actingAs(maker())->getJson('/api/v1/admin/makers')->assertForbidden();
    $this->actingAs($profile->user)->postJson("/api/v1/admin/makers/{$profile->uuid}/approve")->assertForbidden();

    expect($profile->fresh()->status)->toBe(MakerStatus::PendingReview);
});

test('admin approves a pending maker', function () {
    $profile = MakerProfile::factory()->pendingReview()->create();

    $this->actingAs(admin())->postJson("/api/v1/admin/makers/{$profile->uuid}/approve")
        ->assertOk()->assertJsonPath('data.status', 'approved');

    $profile->refresh();
    expect($profile->status)->toBe(MakerStatus::Approved)
        ->and($profile->approved_at)->not->toBeNull()
        ->and($profile->user->isApprovedMaker())->toBeTrue();
});

test('admin rejects with a note and the maker can resubmit', function () {
    $profile = MakerProfile::factory()->pendingReview()->create();

    $this->actingAs(admin())->postJson("/api/v1/admin/makers/{$profile->uuid}/reject", [])
        ->assertStatus(422)->assertJsonValidationErrors('note');

    $this->actingAs(admin())->postJson("/api/v1/admin/makers/{$profile->uuid}/reject", ['note' => 'Додайте історію майстерні'])
        ->assertOk()->assertJsonPath('data.review_note', 'Додайте історію майстерні');

    $this->actingAs($profile->user)->postJson('/api/v1/me/maker-profile/submit')
        ->assertOk()->assertJsonPath('data.status', 'pending_review')
        ->assertJsonPath('data.review_note', null);
});

test('admin cannot approve a draft that was never submitted', function () {
    $profile = MakerProfile::factory()->create();

    $this->actingAs(admin())->postJson("/api/v1/admin/makers/{$profile->uuid}/approve")->assertStatus(409);
});

test('suspended maker disappears from the public list and can be reinstated', function () {
    $profile = MakerProfile::factory()->approved()->create();
    $admin = admin();

    $this->actingAs($admin)->postJson("/api/v1/admin/makers/{$profile->uuid}/suspend", ['note' => 'Скарги покупців'])
        ->assertOk()->assertJsonPath('data.status', 'suspended');

    auth()->forgetGuards();
    $this->getJson('/api/v1/makers')->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/makers/{$profile->slug}")->assertNotFound();

    $this->actingAs($admin)->postJson("/api/v1/admin/makers/{$profile->uuid}/reinstate")
        ->assertOk()->assertJsonPath('data.status', 'approved');
});

test('maker status conflicts are explained in Ukrainian', function () {
    $profile = MakerProfile::factory()->pendingReview()->create();

    $this->actingAs($profile->user)
        ->postJson('/api/v1/me/maker-profile/submit', [], ['Accept-Language' => 'uk'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Цю дію не можна виконати для профілю зі статусом «на перевірці».');
});
