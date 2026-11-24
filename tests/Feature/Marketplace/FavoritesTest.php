<?php

use App\Models\User;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Infrastructure\Persistence\Product;

uses()->group('favorites');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->maker = MakerProfile::factory()->approved()->create();
    $this->vase = Product::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $this->mug = Product::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
});

test('saving a product is idempotent and shows in the list, newest first', function () {
    $this->actingAs($this->user);
    $this->putJson("/api/v1/me/favorites/{$this->vase->uuid}")->assertNoContent();
    $this->travel(1)->seconds();
    $this->putJson("/api/v1/me/favorites/{$this->mug->uuid}")->assertNoContent();
    $this->putJson("/api/v1/me/favorites/{$this->mug->uuid}")->assertNoContent();

    $this->getJson('/api/v1/me/favorites/ids')->assertExactJson(['data' => [$this->mug->uuid, $this->vase->uuid]]);
    $this->getJson('/api/v1/me/favorites')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.uuid', $this->mug->uuid);
});

test('removing a favorite', function () {
    $this->actingAs($this->user)->putJson("/api/v1/me/favorites/{$this->vase->uuid}");
    $this->deleteJson("/api/v1/me/favorites/{$this->vase->uuid}")->assertNoContent();
    $this->deleteJson("/api/v1/me/favorites/{$this->vase->uuid}")->assertNoContent();

    $this->getJson('/api/v1/me/favorites/ids')->assertExactJson(['data' => []]);
});

test('products not on sale cannot be saved and drop out of the list', function () {
    $this->actingAs($this->user);
    $draft = Product::factory()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $this->putJson("/api/v1/me/favorites/{$draft->uuid}")->assertNotFound();

    $this->putJson("/api/v1/me/favorites/{$this->vase->uuid}");
    $this->vase->forceFill(['status' => ProductStatus::Archived])->save();
    $this->getJson('/api/v1/me/favorites')->assertJsonCount(0, 'data');
});

test('favorites are private and need an account', function () {
    $this->actingAs($this->user)->putJson("/api/v1/me/favorites/{$this->vase->uuid}");

    $this->actingAs(User::factory()->create())->getJson('/api/v1/me/favorites/ids')->assertExactJson(['data' => []]);
    auth()->forgetGuards();
    $this->app['auth']->guard('web')->logout();
    $this->getJson('/api/v1/me/favorites')->assertUnauthorized();
});
