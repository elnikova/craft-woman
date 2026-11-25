<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Marketplace\Application\Services\CategoryService;
use Modules\Marketplace\Infrastructure\Persistence\Category;

uses()->group('categories');

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->categories = app(CategoryService::class);
});

test('public tree contains only visible categories with visible children', function () {
    $ceramics = $this->categories->create(['name' => 'Кераміка']);
    $this->categories->create(['name' => 'Посуд', 'parent_uuid' => $ceramics->uuid]);
    $this->categories->create(['name' => 'Прихована', 'parent_uuid' => $ceramics->uuid, 'is_visible' => false]);
    $this->categories->create(['name' => 'Чернетка', 'is_visible' => false]);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Кераміка')
        ->assertJsonCount(1, 'data.0.children')
        ->assertJsonPath('data.0.children.0.name', 'Посуд');
});

test('admin creates a category and a subcategory with unique slugs', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/categories', ['name' => 'Свічки'])
        ->assertCreated()->assertJsonPath('data.slug', 'svicki');

    $parent = Category::where('slug', 'svicki')->first();

    $this->actingAs($this->admin)->postJson('/api/v1/admin/categories', ['name' => 'Свічки', 'parent_uuid' => $parent->uuid])
        ->assertCreated()->assertJsonPath('data.slug', 'svicki-2')->assertJsonPath('data.parent_uuid', $parent->uuid);
});

test('admin index includes hidden categories and usage counts', function () {
    $hidden = $this->categories->create(['name' => 'Прихована', 'is_visible' => false]);
    DB::table('categorizables')->insert([
        'category_uuid' => $hidden->uuid, 'categorizable_uuid' => (string) Str::uuid(), 'categorizable_type' => 'product',
    ]);

    $this->actingAs($this->admin)->getJson('/api/v1/admin/categories')
        ->assertOk()->assertJsonPath('data.0.is_visible', false)->assertJsonPath('data.0.usage_count', 1);
});

test('tree is limited to two levels', function () {
    $a = $this->categories->create(['name' => 'A']);
    $b = $this->categories->create(['name' => 'B', 'parent_uuid' => $a->uuid]);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/categories', ['name' => 'CC', 'parent_uuid' => $b->uuid])
        ->assertStatus(409);

    // A has children → cannot become a child itself
    $d = $this->categories->create(['name' => 'D']);
    $this->actingAs($this->admin)->putJson("/api/v1/admin/categories/{$a->uuid}", ['parent_uuid' => $d->uuid])
        ->assertStatus(409);

    $this->actingAs($this->admin)->putJson("/api/v1/admin/categories/{$a->uuid}", ['parent_uuid' => $a->uuid])
        ->assertStatus(409);
});

test('renaming updates the slug', function () {
    $c = $this->categories->create(['name' => 'Дерево']);

    $this->actingAs($this->admin)->putJson("/api/v1/admin/categories/{$c->uuid}", ['name' => 'Вироби з дерева', 'is_visible' => false])
        ->assertOk()->assertJsonPath('data.slug', 'virobi-z-dereva')->assertJsonPath('data.is_visible', false);
});

test('category with children or usage cannot be deleted; empty one can', function () {
    $parent = $this->categories->create(['name' => 'Текстиль']);
    $child = $this->categories->create(['name' => 'Вишивка', 'parent_uuid' => $parent->uuid]);

    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/categories/{$parent->uuid}")->assertStatus(409);

    DB::table('categorizables')->insert([
        'category_uuid' => $child->uuid, 'categorizable_uuid' => (string) Str::uuid(), 'categorizable_type' => 'product',
    ]);
    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/categories/{$child->uuid}")->assertStatus(409);

    $empty = $this->categories->create(['name' => 'Порожня']);
    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/categories/{$empty->uuid}")->assertNoContent();
    expect(Category::find($empty->uuid))->toBeNull();
});

test('validation errors', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/categories', ['name' => 'x', 'parent_uuid' => 'nope'])
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'parent_uuid']);
});

test('only admins manage categories', function () {
    $maker = User::factory()->create(['role' => Role::Master]);
    $c = $this->categories->create(['name' => 'Шкіра']);

    $this->actingAs($maker)->getJson('/api/v1/admin/categories')->assertForbidden();
    $this->actingAs($maker)->postJson('/api/v1/admin/categories', ['name' => 'Нова'])->assertForbidden();
    $this->actingAs($maker)->deleteJson("/api/v1/admin/categories/{$c->uuid}")->assertForbidden();
});

test('tag autocomplete', function () {
    \Modules\Marketplace\Infrastructure\Persistence\Tag::resolveNames(['Ручна робота', 'ручна РОБОТА', 'вовна', '']);

    $this->getJson('/api/v1/tags?q=ру')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'ручна робота');
    $this->getJson('/api/v1/tags')->assertJsonCount(2, 'data');
});

test('errors are translated to Ukrainian when the client asks for it', function () {
    $parent = $this->categories->create(['name' => 'Текстиль']);
    $this->categories->create(['name' => 'Вишивка', 'parent_uuid' => $parent->uuid]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/admin/categories/{$parent->uuid}", [], ['Accept-Language' => 'uk'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'У категорії «Текстиль» є підкатегорії. Спершу перенесіть або видаліть їх.');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/admin/categories', ['name' => ''], ['Accept-Language' => 'uk'])
        ->assertJsonPath('errors.name.0', 'Заповніть поле «назва категорії».');
});
