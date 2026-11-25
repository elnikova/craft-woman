<?php

use App\Enums\Role;
use App\Models\User;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Workshops\Infrastructure\Persistence\Workshop;
use Modules\Workshops\Infrastructure\Persistence\WorkshopBooking;

uses()->group('workshops');

beforeEach(function () {
    $this->maker = MakerProfile::factory()->approved()->create();
    $this->buyer = User::factory()->create(['role' => Role::Buyer]);
});

/** Switch the signed-in user; the cached guards would otherwise keep the previous one */
function signIn(object $test, User $user): object
{
    app('auth')->forgetGuards();

    return $test->actingAs($user);
}

function checkoutWorkshop(object $test, User $user, Workshop $w, int $seats = 1, bool $pay = true): string
{
    signIn($test, $user)->postJson('/api/v1/cart/items', ['workshop_uuid' => $w->uuid, 'quantity' => $seats])->assertCreated();
    $number = $test->postJson('/api/v1/checkout', ['payment_method' => 'online'])->assertCreated()->json('data.order_number');
    if ($pay) {
        $test->postJson("/api/v1/me/orders/{$number}/test-payment")->assertOk();
    }

    return $number;
}

// ---------- Maker builds a workshop ----------

test('a maker creates a workshop with sessions in its timezone; an admin publishes it', function () {
    signIn($this, $this->maker->user);
    $uuid = $this->postJson('/api/v1/me/workshops', [
        'name' => 'Гончарний круг: перша чашка',
        'description' => 'Три години за кругом у маленькій групі, глина й випал включені.',
        'price_amount_minor' => 4500,
        'format' => 'offline',
        'timezone' => 'Europe/Kyiv',
        'capacity' => 6,
        'location_city' => 'Опішня',
    ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.uuid');

    $this->postJson("/api/v1/me/workshops/{$uuid}/submit", [], ['Accept-Language' => 'uk'])
        ->assertStatus(409)->assertJsonPath('message', 'Майстер-клас ще не готовий до перевірки: місто й адреса, хоча б одне заняття.');

    $date = now('Europe/Kyiv')->addDays(10)->format('Y-m-d');
    $this->postJson("/api/v1/me/workshops/{$uuid}/sessions", ['starts_at' => "{$date}T14:00", 'ends_at' => "{$date}T13:00"])->assertStatus(409);
    $this->postJson("/api/v1/me/workshops/{$uuid}/sessions", ['starts_at' => "{$date}T11:00", 'ends_at' => "{$date}T14:00"])
        ->assertOk()
        ->assertJsonPath('data.sessions.0.starts_local', "{$date}T11:00");
    expect(Workshop::find($uuid)->sessions()->first()->starts_at->format('H:i'))->toBe(now('Europe/Kyiv')->addDays(10)->setTime(11, 0)->utc()->format('H:i'));

    $this->putJson("/api/v1/me/workshops/{$uuid}", ['location_address' => 'вул. Гончарна, 12'])->assertJsonPath('data.missing', []);
    $this->postJson("/api/v1/me/workshops/{$uuid}/submit")->assertOk()->assertJsonPath('data.status', 'pending_review');

    signIn($this, User::factory()->create(['role' => Role::Admin]))->getJson('/api/v1/admin/workshops')->assertJsonCount(1, 'data');
    $this->postJson("/api/v1/admin/workshops/{$uuid}/approve")->assertJsonPath('data.status', 'published');

    $this->getJson('/api/v1/workshops')->assertJsonCount(1, 'data')->assertJsonPath('data.0.seats_left', 6)->assertJsonPath('data.0.is_bookable', true);
});

test('the meeting link and exact address are shown only to participants and the owner', function () {
    $w = Workshop::factory()->published()->online()->create(['maker_profile_uuid' => $this->maker->uuid, 'participant_note' => 'Підготуйте спиці 2,5 мм']);

    $this->getJson("/api/v1/workshops/{$w->slug}")->assertOk()->assertJsonPath('data.private', null)->assertJsonMissing(['meeting_url' => 'https://meet.example.com/abc-defg-hij']);
    signIn($this, $this->maker->user)->getJson("/api/v1/workshops/{$w->slug}")->assertJsonPath('data.private.meeting_url', 'https://meet.example.com/abc-defg-hij');

    checkoutWorkshop($this, $this->buyer, $w);
    $this->getJson("/api/v1/workshops/{$w->slug}")->assertJsonPath('data.my_seats', 1)->assertJsonPath('data.private.note', 'Підготуйте спиці 2,5 мм');
    $this->getJson('/api/v1/me/bookings')->assertJsonCount(1, 'data')->assertJsonPath('data.0.workshop.private.meeting_url', 'https://meet.example.com/abc-defg-hij');
});

// ---------- Seats ----------

test('seats are held at checkout, confirmed on payment and capacity is never exceeded', function () {
    $w = Workshop::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid, 'capacity' => 3]);

    signIn($this, $this->buyer)->postJson('/api/v1/cart/items', ['workshop_uuid' => $w->uuid, 'quantity' => 4], ['Accept-Language' => 'uk'])
        ->assertStatus(409)->assertJsonPath('message', 'Залишилось місць: 3.');
    $this->postJson('/api/v1/cart/items', ['workshop_uuid' => $w->uuid, 'quantity' => 2])->assertCreated()
        ->assertJsonPath('data.groups.0.items.0.kind', 'workshop')
        ->assertJsonPath('data.groups.0.items.0.quantity', 2);
    $this->postJson('/api/v1/checkout/quote')->assertJsonPath('data.needs_address', false)->assertJsonPath('data.payment_methods.1.available', false);
    $number = $this->postJson('/api/v1/checkout', ['payment_method' => 'online'])->assertCreated()->json('data.order_number');

    // Unpaid order already holds the seats
    expect(WorkshopBooking::query()->where('status', 'pending')->sum('seats'))->toBe(2);
    $this->getJson("/api/v1/workshops/{$w->slug}")->assertJsonPath('data.seats_left', 1);

    // Someone else wants two seats → only one is left
    $other = User::factory()->create();
    signIn($this, $other)->postJson('/api/v1/cart/items', ['workshop_uuid' => $w->uuid, 'quantity' => 2])->assertStatus(409);
    checkoutWorkshop($this, $other, $w, 1);
    signIn($this, User::factory()->create())->postJson('/api/v1/cart/items', ['workshop_uuid' => $w->uuid])->assertStatus(409);

    signIn($this, $this->buyer)->postJson("/api/v1/me/orders/{$number}/test-payment")
        ->assertJsonPath('data.groups.0.items.0.is_workshop', true)
        ->assertJsonPath('data.status', 'completed');
    expect(WorkshopBooking::query()->where('status', 'confirmed')->sum('seats'))->toBe(3);

    signIn($this, $this->maker->user)->getJson("/api/v1/me/workshops/{$w->uuid}/participants")
        ->assertJsonCount(2, 'data')->assertJsonPath('meta.seats_confirmed', 3);
});

test('a seat in a cart is re-checked at checkout', function () {
    $w = Workshop::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid, 'capacity' => 1]);
    signIn($this, $this->buyer)->postJson('/api/v1/cart/items', ['workshop_uuid' => $w->uuid])->assertCreated();
    checkoutWorkshop($this, User::factory()->create(), $w);

    signIn($this, $this->buyer)->getJson('/api/v1/cart')->assertJsonPath('data.groups.0.items.0.issue', 'sold_out');
    $this->postJson('/api/v1/checkout', ['payment_method' => 'online'])->assertStatus(409);
});

test('cancelling an unpaid order releases the seats', function () {
    $w = Workshop::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid, 'capacity' => 2]);
    $number = checkoutWorkshop($this, $this->buyer, $w, 2, pay: false);
    $this->getJson("/api/v1/workshops/{$w->slug}")->assertJsonPath('data.seats_left', 0);

    $this->postJson("/api/v1/me/orders/{$number}/cancel")->assertOk();
    $this->getJson("/api/v1/workshops/{$w->slug}")->assertJsonPath('data.seats_left', 2)->assertJsonPath('data.my_seats', 0);
    expect(WorkshopBooking::query()->first()->status->value)->toBe('cancelled');
});

test('cancelling an unpaid order does not grant course access', function () {
    $course = Course::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    signIn($this, $this->buyer)->postJson('/api/v1/cart/items', ['course_uuid' => $course->uuid])->assertCreated();
    $number = $this->postJson('/api/v1/checkout', ['payment_method' => 'online'])->json('data.order_number');

    $this->postJson("/api/v1/me/orders/{$number}/cancel")->assertOk();
    $this->getJson('/api/v1/me/learning')->assertJsonCount(0, 'data');
});

test('booking closes when the first session starts', function () {
    $w = Workshop::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid]);
    $w->sessions()->first()->update(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(2)]);

    signIn($this, $this->buyer)->postJson('/api/v1/cart/items', ['workshop_uuid' => $w->uuid])->assertStatus(409);
    $this->getJson('/api/v1/workshops')->assertJsonCount(0, 'data');
});

// ---------- Maker changes after bookings ----------

test('capacity and format are protected once seats are booked; the maker can cancel the workshop', function () {
    $w = Workshop::factory()->published()->create(['maker_profile_uuid' => $this->maker->uuid, 'capacity' => 5]);
    checkoutWorkshop($this, $this->buyer, $w, 3);

    signIn($this, $this->maker->user);
    $this->putJson("/api/v1/me/workshops/{$w->uuid}", ['capacity' => 2])->assertStatus(409);
    $this->putJson("/api/v1/me/workshops/{$w->uuid}", ['format' => 'online'])->assertStatus(409);
    $this->deleteJson('/api/v1/me/workshop-sessions/'.$w->sessions()->first()->uuid)->assertStatus(409);
    $this->putJson("/api/v1/me/workshops/{$w->uuid}", ['capacity' => 8])->assertOk()->assertJsonPath('data.seats_left', 5);

    $this->postJson("/api/v1/me/workshops/{$w->uuid}/cancel", ['reason' => 'Захворіла, перенесемо на весну'])->assertOk()->assertJsonPath('data.status', 'cancelled');

    signIn($this, $this->buyer)->getJson('/api/v1/me/bookings')
        ->assertJsonPath('data.0.status', 'cancelled')
        ->assertJsonPath('data.0.cancel_reason', 'Захворіла, перенесемо на весну')
        ->assertJsonPath('data.0.workshop.private', null);
    $this->getJson("/api/v1/workshops/{$w->slug}")->assertNotFound();
});

test('makers only manage their own workshops', function () {
    $w = Workshop::factory()->create(['maker_profile_uuid' => $this->maker->uuid]);
    signIn($this, MakerProfile::factory()->approved()->create()->user);

    $this->getJson("/api/v1/me/workshops/{$w->uuid}")->assertNotFound();
    $this->getJson("/api/v1/me/workshops/{$w->uuid}/participants")->assertNotFound();
});
