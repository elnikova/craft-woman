<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Identity\Domain\MakerStatus;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Marketplace\Application\Services\CategoryService;
use Modules\Marketplace\Application\Services\InventoryService;
use Modules\Marketplace\Domain\Exceptions\ProductRuleViolation;
use Modules\Marketplace\Domain\ProductStatus;
use Modules\Marketplace\Infrastructure\Persistence\Product;

uses()->group('products');

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->maker = MakerProfile::factory()->approved()->create();
    $this->category = app(CategoryService::class)->create(['name' => 'Кераміка']);
});

function draftPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'physical',
        'name' => 'Чашка «Ранок»',
        'description' => 'Димлена глина, кожна чашка трохи іншого відтінку.',
        'price_amount_minor' => 2800,
        'currency' => 'EUR',
        'category_uuid' => test()->category->uuid,
        'tags' => ['кераміка', 'ручна робота'],
        'production_time_days' => 7,
    ], $overrides);
}

function makeReady(Product $product): void
{
    test()->actingAs(test()->maker->user)
        ->post("/api/v1/me/products/{$product->uuid}/images", ['image' => UploadedFile::fake()->image('cup.jpg')], ['Accept' => 'application/json'])
        ->assertOk();
}

// ---------- Creating ----------

test('approved maker creates a physical draft with a default variant', function () {
    $this->actingAs($this->maker->user)->postJson('/api/v1/me/products', draftPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.price.amount_minor', 2800)
        ->assertJsonPath('data.category.slug', 'keramika')
        ->assertJsonPath('data.tags', ['кераміка', 'ручна робота'])
        ->assertJsonCount(1, 'data.variants')
        ->assertJsonPath('data.maker.slug', $this->maker->slug);
});

test('maker that is not approved cannot create products', function () {
    $pending = MakerProfile::factory()->pendingReview()->create();

    $this->actingAs($pending->user)->postJson('/api/v1/me/products', draftPayload(), ['Accept-Language' => 'uk'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Додавати товари можуть лише майстри зі схваленим профілем.');
});

test('users without a maker profile cannot use the product studio', function () {
    $buyer = User::factory()->create(['role' => Role::Buyer]);
    $this->actingAs($buyer)->postJson('/api/v1/me/products', draftPayload())->assertForbidden();
});

test('variants are synced: update, add, remove', function () {
    $product = Product::factory()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $default = $product->variants()->first();

    $this->actingAs($this->maker->user)->putJson("/api/v1/me/products/{$product->uuid}", draftPayload([
        'variants' => [
            ['uuid' => $default->uuid, 'name' => 'Біла', 'stock' => 4],
            ['name' => 'Чорна', 'stock' => 0, 'price_amount_minor' => 3200],
        ],
    ]))->assertOk()
        ->assertJsonCount(2, 'data.variants')
        ->assertJsonPath('data.variants.0.uuid', $default->uuid)
        ->assertJsonPath('data.variants.1.price.amount_minor', 3200)
        ->assertJsonPath('data.variants.1.in_stock', false);

    $this->actingAs($this->maker->user)->putJson("/api/v1/me/products/{$product->uuid}", draftPayload(['variants' => []]))
        ->assertStatus(422); // at least one variant (validation min:1)
});

test('digital products have no variants and need a file', function () {
    $id = $this->actingAs($this->maker->user)->postJson('/api/v1/me/products', draftPayload(['type' => 'digital', 'name' => 'Схема «Маки»']))
        ->assertCreated()->assertJsonCount(0, 'data.variants')->json('data.uuid');

    $product = Product::find($id);
    makeReady($product);

    $this->actingAs($this->maker->user)->postJson("/api/v1/me/products/{$id}/submit", [], ['Accept-Language' => 'uk'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Щоб надіслати товар на перевірку, додайте: хоча б один файл для завантаження.');

    $this->actingAs($this->maker->user)
        ->post("/api/v1/me/products/{$id}/files", ['file' => UploadedFile::fake()->create('maky.pdf', 300, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.files.0.name', 'maky.pdf');

    Storage::disk('local')->assertExists($product->files()->first()->storage_path);

    $this->actingAs($this->maker->user)->postJson("/api/v1/me/products/{$id}/submit")
        ->assertOk()->assertJsonPath('data.status', 'pending_review');
});

// ---------- Ownership ----------

test('a maker cannot touch another maker\'s product', function () {
    $other = Product::factory()->create();

    $this->actingAs($this->maker->user)->getJson("/api/v1/me/products/{$other->uuid}")->assertForbidden();
    $this->actingAs($this->maker->user)->putJson("/api/v1/me/products/{$other->uuid}", draftPayload())->assertForbidden();
    $this->actingAs($this->maker->user)->deleteJson("/api/v1/me/products/{$other->uuid}")->assertForbidden();
});

// ---------- Review flow ----------

test('submit requires a complete product', function () {
    $product = Product::factory()->create(['maker_profile_uuid' => $this->maker->uuid, 'price_amount_minor' => 0]);

    $this->actingAs($this->maker->user)->postJson("/api/v1/me/products/{$product->uuid}/submit", [], ['Accept-Language' => 'uk'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Щоб надіслати товар на перевірку, додайте: ціну, категорію, хоча б одне фото.');
});

test('full review flow: submit → admin approves → public; rejected → fix → resubmit', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $id = $this->actingAs($this->maker->user)->postJson('/api/v1/me/products', draftPayload())->json('data.uuid');
    $product = Product::find($id);
    makeReady($product);

    $this->actingAs($this->maker->user)->postJson("/api/v1/me/products/{$id}/submit")->assertOk();

    // Locked while in review
    $this->actingAs($this->maker->user)->putJson("/api/v1/me/products/{$id}", draftPayload())->assertStatus(409);

    $this->actingAs($admin)->postJson("/api/v1/admin/products/{$id}/reject", ['note' => 'Додайте фото збоку'])
        ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.review_note', 'Додайте фото збоку');

    $this->actingAs($this->maker->user)->postJson("/api/v1/me/products/{$id}/submit")->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/admin/products/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'published');

    auth()->forgetGuards();
    $this->getJson('/api/v1/products')->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $id);
    $this->getJson("/api/v1/products/{$product->slug}")->assertOk()->assertJsonMissingPath('data.status');
});

test('only admins moderate products', function () {
    $product = Product::factory()->status(ProductStatus::PendingReview)->create(['maker_profile_uuid' => $this->maker->uuid]);

    $this->actingAs($this->maker->user)->postJson("/api/v1/admin/products/{$product->uuid}/approve")->assertForbidden();
    expect($product->fresh()->status)->toBe(ProductStatus::PendingReview);
});

test('archive and relist; only drafts can be deleted', function () {
    $product = Product::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);

    $this->actingAs($this->maker->user)->deleteJson("/api/v1/me/products/{$product->uuid}")->assertStatus(409);
    $this->actingAs($this->maker->user)->postJson("/api/v1/me/products/{$product->uuid}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
    $this->actingAs($this->maker->user)->postJson("/api/v1/me/products/{$product->uuid}/relist")->assertOk()->assertJsonPath('data.status', 'draft');

    $draft = Product::factory()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $this->actingAs($this->maker->user)->deleteJson("/api/v1/me/products/{$draft->uuid}")->assertNoContent();
    expect(Product::withTrashed()->find($draft->uuid))->toBeNull();
});

test('slug stays stable after the product was published', function () {
    $product = Product::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $slug = $product->slug;

    $this->actingAs($this->maker->user)->putJson("/api/v1/me/products/{$product->uuid}", draftPayload(['name' => 'Нова назва']))
        ->assertOk()->assertJsonPath('data.slug', $slug)->assertJsonPath('data.status', 'published');
});

// ---------- Public catalog ----------

test('catalog shows only published products of approved makers', function () {
    $visible = Product::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    Product::factory()->create(['maker_profile_uuid' => $this->maker->uuid]); // draft
    $suspendedMaker = MakerProfile::factory()->status(MakerStatus::Suspended)->create();
    $hidden = Product::factory()->published()->create(['maker_profile_uuid' => $suspendedMaker->uuid]);

    $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $visible->uuid);
    $this->getJson("/api/v1/products/{$hidden->slug}")->assertNotFound();
});

test('catalog filters by category including subcategories, type, maker, search and sorts by price', function () {
    $sub = app(CategoryService::class)->create(['name' => 'Посуд', 'parent_uuid' => $this->category->uuid]);
    $cup = Product::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid, 'name' => 'Чашка', 'price_amount_minor' => 2800]);
    $cup->categories()->attach($sub->uuid);
    $pattern = Product::factory()->digital()->published()->create(['name' => 'Схема', 'price_amount_minor' => 900]);

    $this->getJson('/api/v1/products?category=keramika')->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $cup->uuid);
    $this->getJson('/api/v1/products?type=digital')->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $pattern->uuid);
    $this->getJson("/api/v1/products?maker={$this->maker->slug}")->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/products?q=Чаш')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/products?sort=price_asc')->assertJsonPath('data.0.uuid', $pattern->uuid);
    $this->getJson('/api/v1/products?sort=price_desc')->assertJsonPath('data.0.uuid', $cup->uuid);
});

test('public product hides exact stock unless it is low', function () {
    $product = Product::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $product->variants()->first()->update(['stock' => 25, 'sku' => 'CUP-1']);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertJsonPath('data.variants.0.stock', null)
        ->assertJsonPath('data.variants.0.in_stock', true)
        ->assertJsonMissingPath('data.variants.0.sku');

    $product->variants()->first()->update(['stock' => 2]);
    $this->getJson("/api/v1/products/{$product->slug}")->assertJsonPath('data.variants.0.stock', 2);
});

// ---------- Media ----------

test('images are stored, ordered and removed', function () {
    $product = Product::factory()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $user = $this->maker->user;

    foreach (['a.jpg', 'b.jpg'] as $name) {
        $this->actingAs($user)->post("/api/v1/me/products/{$product->uuid}/images", ['image' => UploadedFile::fake()->image($name)], ['Accept' => 'application/json'])->assertOk();
    }
    [$a, $b] = $product->images()->get()->all();
    Storage::disk('public')->assertExists($a->storage_path);

    $this->actingAs($user)->putJson("/api/v1/me/products/{$product->uuid}/images/order", ['order' => [$b->uuid, $a->uuid]])
        ->assertOk()->assertJsonPath('data.images.0.uuid', $b->uuid);

    $this->actingAs($user)->deleteJson("/api/v1/me/products/{$product->uuid}/images/{$a->uuid}")->assertOk()->assertJsonCount(1, 'data.images');
    Storage::disk('public')->assertMissing($a->storage_path);

    $this->actingAs($user)->post("/api/v1/me/products/{$product->uuid}/images", ['image' => UploadedFile::fake()->create('x.exe', 10)], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

// ---------- Inventory ----------

test('inventory never goes negative', function () {
    $product = Product::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $variant = $product->variants()->first();
    $variant->update(['stock' => 2]);
    $inventory = app(InventoryService::class);

    expect($inventory->decrement($variant->uuid, 2)->stock)->toBe(0);
    expect(fn () => $inventory->decrement($variant->uuid, 1))->toThrow(ProductRuleViolation::class);
    expect($variant->fresh()->stock)->toBe(0);
});
