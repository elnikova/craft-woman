<?php

use App\Enums\Role;
use App\Models\User;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Modules\Marketplace\Infrastructure\Persistence\Product;

uses()->group('i18n');

beforeEach(function () {
    $this->maker = MakerProfile::factory()->approved()->create(['bio' => 'Кераміка з Опішні']);
    $this->maker->forceFill(['translations' => ['en' => ['bio' => 'Ceramics from Opishnia']]])->save();
    $this->product = Product::factory()->published()->create([
        'maker_profile_uuid' => $this->maker->uuid,
        'name' => 'Чашка «Ранок»',
        'slug' => 'chashka-ranok',
    ]);
    $this->product->forceFill(['translations' => ['en' => ['name' => '"Morning" mug']]])->save();
});

test('buyers get the translation in their language and the source otherwise', function () {
    $this->getJson('/api/v1/products/chashka-ranok', ['Accept-Language' => 'en'])
        ->assertJsonPath('data.name', '"Morning" mug')
        ->assertJsonPath('data.content_locale', 'en');

    $this->getJson('/api/v1/products/chashka-ranok', ['Accept-Language' => 'uk'])
        ->assertJsonPath('data.name', 'Чашка «Ранок»')
        ->assertJsonPath('data.content_locale', 'uk');
});

test('without a translation the source text comes back, marked with its language', function () {
    $this->product->forceFill(['translations' => null])->save();

    $this->getJson('/api/v1/products/chashka-ranok', ['Accept-Language' => 'en'])
        ->assertJsonPath('data.name', 'Чашка «Ранок»')
        ->assertJsonPath('data.content_locale', 'uk');
});

test('maker profiles are translated too', function () {
    $this->getJson("/api/v1/makers/{$this->maker->slug}", ['Accept-Language' => 'en'])
        ->assertJsonPath('data.bio', 'Ceramics from Opishnia');
});

test('the maker saves an English version with the product; empty and unknown entries are dropped', function () {
    $this->actingAs($this->maker->user);
    $product = Product::factory()->create(['maker_profile_uuid' => $this->maker->uuid]);

    $this->putJson("/api/v1/me/products/{$product->uuid}", [
        'type' => 'physical',
        'name' => 'Глечик',
        'description' => 'Для молока',
        'price_amount_minor' => 4600,
        'currency' => 'EUR',
        'translations' => [
            'en' => ['name' => 'Milk jug', 'description' => '  '],
            'uk' => ['name' => 'same as source, ignored'],
            'de' => ['name' => 'Krug'],
        ],
    ])->assertOk()
        ->assertJsonPath('data.name', 'Глечик')
        ->assertJsonPath('data.translations.en.name', 'Milk jug');

    expect($product->fresh()->translations)->toBe(['en' => ['name' => 'Milk jug']]);
});

test('categories are bilingual; admins see source and translations', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin)->postJson('/api/v1/admin/categories', [
        'name' => 'Кераміка',
        'translations' => ['en' => ['name' => 'Ceramics']],
    ])->assertCreated();

    $this->getJson('/api/v1/categories', ['Accept-Language' => 'en'])->assertJsonFragment(['name' => 'Ceramics']);
    $this->getJson('/api/v1/admin/categories', ['Accept-Language' => 'en'])
        ->assertJsonFragment(['translations' => ['en' => ['name' => 'Ceramics']]])
        ->assertJsonFragment(['locale' => 'uk', 'name' => 'Кераміка', 'description' => null]);

    expect(Category::where('name', 'Кераміка')->first()->translate('name', 'en'))->toBe('Ceramics');
});

test('users store their interface language', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->patchJson('/api/v1/me/account', ['locale' => 'en'])->assertOk()->assertJsonPath('data.locale', 'en');
    $this->patchJson('/api/v1/me/account', ['locale' => 'fr'])->assertJsonValidationErrors('locale');
});

test('registration remembers the language of the request', function () {
    $this->postJson('/api/v1/register', [
        'name' => 'Anna', 'email' => 'anna@test.local', 'password' => 'password123', 'password_confirmation' => 'password123',
    ], ['Accept-Language' => 'en'])->assertCreated()->assertJsonPath('data.locale', 'en');
});

test('catalog search finds products by their English name', function () {
    $this->getJson('/api/v1/products?q=Morning', ['Accept-Language' => 'en'])->assertJsonCount(1, 'data');
});

test('owners see the reader language on public pages and the source text in the editor', function () {
    $this->actingAs($this->maker->user);

    $this->getJson('/api/v1/products/chashka-ranok', ['Accept-Language' => 'en'])->assertJsonPath('data.name', '"Morning" mug');
    $this->getJson("/api/v1/me/products/{$this->product->uuid}", ['Accept-Language' => 'en'])
        ->assertJsonPath('data.name', 'Чашка «Ранок»')
        ->assertJsonPath('data.translations.en.name', '"Morning" mug');
    $this->getJson('/api/v1/me/maker-profile', ['Accept-Language' => 'en'])->assertJsonPath('data.bio', 'Кераміка з Опішні');
});
