<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Education\Infrastructure\Persistence\Enrollment;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

uses()->group('education');

beforeEach(function () {
    $this->maker = MakerProfile::factory()->approved()->create();
    $this->buyer = User::factory()->create(['role' => Role::Buyer]);
});

function buyCourse(object $test, Course $course): string
{
    $test->actingAs($test->buyer)->postJson('/api/v1/cart/items', ['course_uuid' => $course->uuid])->assertCreated();
    $number = $test->postJson('/api/v1/checkout', ['payment_method' => 'online'])->assertCreated()->json('data.order_number');
    $test->postJson("/api/v1/me/orders/{$number}/test-payment")->assertOk();

    return $number;
}

// ---------- Maker builds a course ----------

test('a maker builds a course, submits it and an admin publishes it', function () {
    $this->actingAs($this->maker->user);
    $uuid = $this->postJson('/api/v1/me/courses', [
        'name' => 'Вишивка хрестиком з нуля',
        'description' => 'Від вибору канви до першої серветки: покроково, з відео й схемами.',
        'price_amount_minor' => 3900,
        'access_type' => 'limited',
        'access_days' => 90,
        'translations' => ['en' => ['name' => 'Cross-stitch from scratch']],
    ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.uuid');

    // Not ready: no lessons yet
    $this->postJson("/api/v1/me/courses/{$uuid}/submit", [], ['Accept-Language' => 'uk'])
        ->assertStatus(409)->assertJsonPath('message', 'Курс ще не готовий до перевірки: хоча б один урок.');

    $module = $this->postJson("/api/v1/me/courses/{$uuid}/modules", ['title' => 'Підготовка'])->assertCreated()->json('data.modules.0.uuid');
    $lesson = $this->postJson("/api/v1/me/course-modules/{$module}/lessons", ['title' => 'Канва і нитки', 'is_preview' => true])->json('data.modules.0.lessons.0.uuid');
    $this->postJson("/api/v1/me/lessons/{$lesson}/materials", ['type' => 'video', 'external_url' => 'https://example.com/x.mp4'])->assertStatus(409);
    $this->postJson("/api/v1/me/lessons/{$lesson}/materials", ['type' => 'video', 'external_url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->assertCreated()
        ->assertJsonPath('data.modules.0.lessons.0.materials.0.embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->assertJsonPath('data.missing', []);

    $this->postJson("/api/v1/me/courses/{$uuid}/submit")->assertOk()->assertJsonPath('data.status', 'pending_review');
    $this->putJson("/api/v1/me/courses/{$uuid}", ['name' => 'Інша назва'])->assertStatus(409);

    $this->actingAs(User::factory()->create(['role' => Role::Admin]))->getJson('/api/v1/admin/courses')->assertJsonCount(1, 'data');
    $this->postJson("/api/v1/admin/courses/{$uuid}/approve")->assertOk()->assertJsonPath('data.status', 'published');

    $this->getJson('/api/v1/courses', ['Accept-Language' => 'en'])->assertJsonPath('data.0.name', 'Cross-stitch from scratch');
});

test('makers only see and change their own courses', function () {
    $course = Course::factory()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $other = MakerProfile::factory()->approved()->create();

    $this->actingAs($other->user)->getJson("/api/v1/me/courses/{$course->uuid}")->assertNotFound();
    $this->putJson("/api/v1/me/courses/{$course->uuid}", ['name' => 'Чужий курс'])->assertNotFound();
});

test('course cover and lesson files are uploaded; files stay private', function () {
    Storage::fake('public');
    Storage::fake('local');
    $course = Course::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $lesson = $course->modules()->first()->lessons()->where('is_preview', false)->first();

    $this->actingAs($this->maker->user)->post("/api/v1/me/courses/{$course->uuid}/cover", ['cover' => UploadedFile::fake()->image('c.jpg')], ['Accept' => 'application/json'])->assertOk();
    $this->post("/api/v1/me/lessons/{$lesson->uuid}/materials", ['type' => 'file', 'file' => UploadedFile::fake()->create('schema.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
    $material = $lesson->materials()->where('type', 'file')->first();

    $url = "/api/v1/courses/{$course->slug}/lessons/{$lesson->uuid}/materials/{$material->uuid}/file";
    $this->actingAs($this->buyer)->get($url)->assertForbidden();
    buyCourse($this, $course);
    $this->get($url)->assertOk()->assertDownload('schema.pdf');
});

// ---------- Public pages ----------

test('guests see the outline and free previews, not paid lessons', function () {
    $course = Course::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    [$preview, $paid] = $course->modules()->first()->lessons()->get()->all();

    $this->getJson("/api/v1/courses/{$course->slug}")
        ->assertOk()
        ->assertJsonPath('data.lessons_count', 2)
        ->assertJsonPath('data.modules.0.lessons.1.title', 'Перші петлі')
        ->assertJsonPath('data.enrollment', null);
    $this->getJson("/api/v1/courses/{$course->slug}/lessons/{$preview->uuid}")->assertOk()->assertJsonPath('data.materials.0.content', 'Вітаю на курсі!');
    $this->getJson("/api/v1/courses/{$course->slug}/lessons/{$paid->uuid}")->assertForbidden();

    $draft = Course::factory()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $this->getJson("/api/v1/courses/{$draft->slug}")->assertNotFound();
});

// ---------- Buying and learning ----------

test('buying a course grants access after payment; courses need prepayment', function () {
    $course = Course::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $paid = $course->modules()->first()->lessons()->where('is_preview', false)->first();

    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['course_uuid' => $course->uuid])->assertCreated()->assertJsonPath('data.groups.0.items.0.kind', 'course');
    $this->postJson('/api/v1/checkout/quote')->assertJsonPath('data.needs_address', false)->assertJsonPath('data.payment_methods.1.available', false);
    $number = $this->postJson('/api/v1/checkout', ['payment_method' => 'online'])->json('data.order_number');

    // Not paid yet → no access
    $this->getJson("/api/v1/courses/{$course->slug}/lessons/{$paid->uuid}")->assertForbidden();
    $this->postJson("/api/v1/me/orders/{$number}/test-payment")->assertJsonPath('data.status', 'completed')->assertJsonPath('data.groups.0.items.0.is_course', true);

    $this->getJson("/api/v1/courses/{$course->slug}/lessons/{$paid->uuid}")->assertOk()->assertJsonPath('data.materials.0.provider', 'youtube');
    $this->getJson('/api/v1/me/learning')->assertJsonCount(1, 'data')->assertJsonPath('data.0.has_access', true)->assertJsonPath('data.0.percent', 0);

    // Lifetime access: can't buy it twice
    $this->postJson('/api/v1/cart/items', ['course_uuid' => $course->uuid])->assertStatus(409);
});

test('completing every required lesson finishes the course and unlocks the certificate', function () {
    $course = Course::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    [$first, $second] = $course->modules()->first()->lessons()->get()->all();
    $first->update(['is_required' => false]);
    buyCourse($this, $course);

    $this->postJson("/api/v1/courses/{$course->slug}/certificate")->assertStatus(409);
    $this->postJson("/api/v1/courses/{$course->slug}/lessons/{$first->uuid}/complete")->assertJsonPath('data.completed_at', null)->assertJsonPath('data.percent', 50);
    $this->postJson("/api/v1/courses/{$course->slug}/lessons/{$second->uuid}/complete")->assertJsonPath('data.percent', 100)->assertJsonPath('data.required_completed', 1);
    expect(Enrollment::first()->completed_at)->not->toBeNull();

    $cert = $this->postJson("/api/v1/courses/{$course->slug}/certificate")->assertCreated()->json('data');
    expect($cert['holder_name'])->toBe($this->buyer->name)->and($cert['number'])->toStartWith('CWC-');
    $this->postJson("/api/v1/courses/{$course->slug}/certificate")->assertJsonPath('data.number', $cert['number']);

    auth()->forgetGuards();
    $this->getJson("/api/v1/certificates/{$cert['number']}")->assertOk()->assertJsonPath('data.course_name', $course->name);
    $this->getJson('/api/v1/certificates/CWC-0000-NOPE00')->assertNotFound();
});

test('courses without certificates never issue one', function () {
    $course = Course::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid, 'certificate_enabled' => false]);
    buyCourse($this, $course);
    foreach ($course->modules()->first()->lessons as $lesson) {
        $this->postJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->uuid}/complete");
    }
    $this->postJson("/api/v1/courses/{$course->slug}/certificate")->assertStatus(409);
});

test('limited access expires but progress stays; buying again extends it', function () {
    $course = Course::factory()->published()->limited(30)->create(['maker_profile_uuid' => $this->maker->uuid]);
    $lesson = $course->modules()->first()->lessons()->where('is_preview', false)->first();
    buyCourse($this, $course);
    $this->postJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->uuid}/complete")->assertOk();

    $this->travel(31)->days();
    $this->getJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->uuid}")->assertForbidden();
    $this->postJson("/api/v1/courses/{$course->slug}/lessons/{$lesson->uuid}/complete", [], ['Accept-Language' => 'uk'])
        ->assertStatus(409)->assertJsonPath('message', 'Доступ до курсу завершився. Його можна продовжити, купивши курс ще раз.');
    $this->getJson('/api/v1/me/learning')->assertJsonPath('data.0.has_access', false)->assertJsonPath('data.0.completed', 1);

    buyCourse($this, $course);
    expect(Enrollment::count())->toBe(1)
        ->and(Enrollment::first()->expires_at->isFuture())->toBeTrue();
    $this->getJson('/api/v1/me/learning')->assertJsonPath('data.0.has_access', true)->assertJsonPath('data.0.completed', 1);
});
