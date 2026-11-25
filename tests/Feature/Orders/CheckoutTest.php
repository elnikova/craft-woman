<?php

use App\Enums\Role;
use App\Models\User;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Marketplace\Infrastructure\Persistence\Product;
use Modules\Orders\Infrastructure\Persistence\Order;
use Modules\Orders\Infrastructure\Persistence\OrderGroup;

uses()->group('orders');

beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => Role::Buyer]);
    $this->makerA = MakerProfile::factory()->approved()->create(['country_code' => 'UA']);
    $this->makerA->forceFill(['cod_enabled' => true, 'delivery_domestic_minor' => 400, 'delivery_international_minor' => 1500])->save();
    $this->makerB = MakerProfile::factory()->approved()->create(['country_code' => 'UA']);
    $this->makerB->forceFill(['cod_enabled' => false, 'delivery_domestic_minor' => 300, 'delivery_international_minor' => null])->save();

    $this->cup = Product::factory()->published()->create(['maker_profile_uuid' => $this->makerA->uuid, 'price_amount_minor' => 2800]);
    $this->cup->variants()->first()->update(['stock' => 3]);
    $this->wallet = Product::factory()->published()->create(['maker_profile_uuid' => $this->makerB->uuid, 'price_amount_minor' => 6200]);
    $this->pattern = Product::factory()->digital()->published()->create(['maker_profile_uuid' => $this->makerA->uuid, 'price_amount_minor' => 900]);
});

function address(array $o = []): array
{
    return array_merge(['recipient_name' => 'Дарія', 'phone' => '+380501234567', 'country_code' => 'UA', 'city' => 'Львів', 'address' => 'вул. Городоцька, 1', 'postal_code' => '79000'], $o);
}

// ---------- Cart ----------

test('guest cart works with a token and is claimed after login', function () {
    $res = $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid, 'quantity' => 2])->assertCreated();
    $token = $res->json('data.token');
    expect($token)->not->toBeNull()->and($res->json('data.items_count'))->toBe(2);

    $this->getJson('/api/v1/cart', ['X-Cart-Token' => $token])->assertJsonPath('data.subtotal_minor', 5600);

    $this->actingAs($this->buyer)->postJson('/api/v1/cart/claim', [], ['X-Cart-Token' => $token])
        ->assertOk()->assertJsonPath('data.items_count', 2)->assertJsonPath('data.token', null);
});

test('cart groups items by maker and keeps one currency', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid])->assertCreated();
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->wallet->uuid])->assertCreated()->assertJsonCount(2, 'data.groups');

    $uah = Product::factory()->published()->create(['currency' => 'UAH']);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $uah->uuid], ['Accept-Language' => 'uk'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'У кошику товари в EUR, а цей товар у UAH. В одному замовленні може бути лише одна валюта.');
});

test('cart refuses more than the stock and unpublished products', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid, 'quantity' => 4])->assertStatus(409);

    $draft = Product::factory()->create(['maker_profile_uuid' => $this->makerA->uuid]);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $draft->uuid])->assertStatus(409);
});

test('digital items are always quantity 1', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->pattern->uuid, 'quantity' => 5])->assertJsonPath('data.items_count', 1);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->pattern->uuid])->assertJsonPath('data.items_count', 1);
});

test('cart flags items that became unavailable and refreshes changed prices', function () {
    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);
    $this->cup->update(['price_amount_minor' => 3000]);
    $this->cup->variants()->first()->update(['stock' => 0]);

    $this->getJson('/api/v1/cart')
        ->assertJsonPath('data.groups.0.items.0.issue', 'sold_out')
        ->assertJsonPath('data.groups.0.items.0.price_changed', true)
        ->assertJsonPath('data.groups.0.items.0.unit_price.amount_minor', 3000)
        ->assertJsonPath('data.has_issues', true);
});

// ---------- Quote & payment rules ----------

test('quote adds one delivery fee per maker and lists payment options', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid, 'quantity' => 2]);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->wallet->uuid]);

    $this->postJson('/api/v1/checkout/quote', ['country_code' => 'UA'], ['Accept-Language' => 'uk'])
        ->assertOk()
        ->assertJsonPath('data.subtotal.amount_minor', 5600 + 6200)
        ->assertJsonPath('data.delivery.amount_minor', 400 + 300)
        ->assertJsonPath('data.total.amount_minor', 11800 + 700)
        ->assertJsonPath('data.payment_methods.1.method', 'cash_on_delivery')
        ->assertJsonPath('data.payment_methods.1.available', false) // maker B doesn't accept COD
        ->assertJsonPath('data.payment_methods.1.reasons.0', 'не всі майстри приймають оплату при отриманні');
});

test('payment on delivery is refused server-side when a maker does not allow it', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->wallet->uuid]);

    $this->postJson('/api/v1/checkout', ['payment_method' => 'cash_on_delivery', 'delivery' => address()])->assertStatus(409);
    expect(Order::count())->toBe(0);
});

test('international delivery forbids COD and uses the international fee', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);

    $this->postJson('/api/v1/checkout/quote', ['country_code' => 'BG'])
        ->assertJsonPath('data.delivery.amount_minor', 1500)
        ->assertJsonPath('data.payment_methods.1.available', false);

    $this->postJson('/api/v1/checkout', ['payment_method' => 'cash_on_delivery', 'delivery' => address(['country_code' => 'BG'])])->assertStatus(409);
});

test('maker that does not ship abroad blocks international checkout', function () {
    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->wallet->uuid]);

    $this->postJson('/api/v1/checkout/quote', ['country_code' => 'PL'])->assertStatus(409);
});

// ---------- Placing orders ----------

test('placing a multi-maker order splits it by maker, snapshots items, reserves stock and clears the cart', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid, 'quantity' => 2]);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->wallet->uuid]);

    $res = $this->postJson('/api/v1/checkout', ['payment_method' => 'online', 'delivery' => address(), 'expected_total_minor' => 12500])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.total.amount_minor', 12500)
        ->assertJsonCount(2, 'data.groups');

    expect($res->json('data.order_number'))->toStartWith('CW-');
    expect($this->cup->variants()->first()->stock)->toBe(1);
    $this->getJson('/api/v1/cart')->assertJsonPath('data.items_count', 0);

    // Snapshot survives product changes
    $this->cup->update(['name' => 'Інша назва', 'price_amount_minor' => 9999]);
    $this->getJson('/api/v1/me/orders/'.$res->json('data.order_number'))
        ->assertJsonPath('data.groups.0.items.0.name', $res->json('data.groups.0.items.0.name'))
        ->assertJsonPath('data.groups.0.items.0.unit_price.amount_minor', 2800);
});

test('checkout refuses when the total changed since review', function () {
    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);

    $this->postJson('/api/v1/checkout', ['payment_method' => 'online', 'delivery' => address(), 'expected_total_minor' => 1])->assertStatus(409);
});

test('address is required for physical items, not for digital-only orders', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);
    $this->postJson('/api/v1/checkout', ['payment_method' => 'online'])->assertStatus(422)->assertJsonValidationErrors(['delivery.city', 'delivery.phone']);

    $this->deleteJson('/api/v1/cart/items/'.$this->getJson('/api/v1/cart')->json('data.groups.0.items.0.uuid'));
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->pattern->uuid]);
    $this->postJson('/api/v1/checkout', ['payment_method' => 'online'])->assertCreated()->assertJsonPath('data.groups.0.shipment', null);
});

test('payment on delivery goes straight to the makers', function () {
    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);

    $this->postJson('/api/v1/checkout', ['payment_method' => 'cash_on_delivery', 'delivery' => address()])
        ->assertCreated()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.groups.0.status', 'awaiting_shipment')
        ->assertJsonPath('data.is_paid', false);
});

test('checkout requires an account', function () {
    $this->postJson('/api/v1/checkout', ['payment_method' => 'online'])->assertUnauthorized();
});

// ---------- After checkout ----------

test('paying unlocks digital downloads and sends physical parts to makers', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->pattern->uuid]);
    $number = $this->postJson('/api/v1/checkout', ['payment_method' => 'online', 'delivery' => address()])->json('data.order_number');

    $this->getJson("/api/v1/me/orders/{$number}")->assertJsonPath('downloads', []);

    $this->postJson("/api/v1/me/orders/{$number}/test-payment")
        ->assertOk()
        ->assertJsonPath('data.is_paid', true)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.groups.0.status', 'awaiting_shipment');
});

test('customer cancels an unpaid order and stock comes back', function () {
    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid, 'quantity' => 3]);
    $number = $this->postJson('/api/v1/checkout', ['payment_method' => 'online', 'delivery' => address()])->json('data.order_number');
    expect($this->cup->variants()->first()->stock)->toBe(0);

    $this->postJson("/api/v1/me/orders/{$number}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($this->cup->variants()->first()->stock)->toBe(3);
});

test('customers see only their own orders', function () {
    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);
    $number = $this->postJson('/api/v1/checkout', ['payment_method' => 'online', 'delivery' => address()])->json('data.order_number');

    $this->actingAs(User::factory()->create())->getJson("/api/v1/me/orders/{$number}")->assertNotFound();
});

// ---------- Maker fulfilment ----------

test('each maker sees and ships only their own part; the order status follows', function () {
    $this->actingAs($this->buyer);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);
    $this->postJson('/api/v1/cart/items', ['product_uuid' => $this->wallet->uuid]);
    $number = $this->postJson('/api/v1/checkout', ['payment_method' => 'online', 'delivery' => address()])->json('data.order_number');
    $this->postJson("/api/v1/me/orders/{$number}/test-payment");

    $groupA = OrderGroup::where('maker_profile_uuid', $this->makerA->uuid)->first();
    $groupB = OrderGroup::where('maker_profile_uuid', $this->makerB->uuid)->first();

    $this->actingAs($this->makerA->user)->getJson('/api/v1/me/sales')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', $number)
        ->assertJsonPath('data.0.shipment.city', 'Львів');

    // Maker A cannot touch Maker B's part
    $this->actingAs($this->makerA->user)->postJson("/api/v1/me/sales/{$groupB->uuid}/ship")->assertNotFound();

    $this->actingAs($this->makerA->user)->postJson("/api/v1/me/sales/{$groupA->uuid}/ship", ['tracking_number' => '20450012345678'])
        ->assertOk()->assertJsonPath('data.status', 'shipped')->assertJsonPath('data.shipment.tracking_number', '20450012345678');
    expect(Order::first()->status->value)->toBe('partially_shipped');

    $this->actingAs($this->makerB->user)->postJson("/api/v1/me/sales/{$groupB->uuid}/ship")->assertOk();
    expect(Order::first()->status->value)->toBe('shipped');

    $this->actingAs($this->makerA->user)->postJson("/api/v1/me/sales/{$groupA->uuid}/deliver")->assertOk();
    $this->actingAs($this->makerB->user)->postJson("/api/v1/me/sales/{$groupB->uuid}/deliver")->assertOk();
    expect(Order::first()->status->value)->toBe('completed');
});

test('maker cancels their part with a reason and stock is returned', function () {
    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid, 'quantity' => 2]);
    $this->postJson('/api/v1/checkout', ['payment_method' => 'cash_on_delivery', 'delivery' => address()]);
    $group = OrderGroup::first();

    $this->actingAs($this->makerA->user)->postJson("/api/v1/me/sales/{$group->uuid}/cancel", ['reason' => 'Розбилась при пакуванні'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancel_reason', 'Розбилась при пакуванні');

    expect($this->cup->variants()->first()->stock)->toBe(3)
        ->and(Order::first()->status->value)->toBe('cancelled');
});

test('admins see all orders, others cannot', function () {
    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->cup->uuid]);
    $this->postJson('/api/v1/checkout', ['payment_method' => 'online', 'delivery' => address()]);

    $this->actingAs(User::factory()->create(['role' => Role::Admin]))->getJson('/api/v1/admin/orders')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($this->buyer)->getJson('/api/v1/admin/orders')->assertForbidden();
});

test('digital files can be downloaded only after payment', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::disk('local')->put('product-files/p/maky.pdf', 'PDF');
    $file = $this->pattern->files()->create([
        'file_name' => 'maky.pdf', 'storage_disk' => 'local', 'storage_path' => 'product-files/p/maky.pdf', 'mime_type' => 'application/pdf', 'file_size' => 3,
    ]);

    $this->actingAs($this->buyer)->postJson('/api/v1/cart/items', ['product_uuid' => $this->pattern->uuid]);
    $order = $this->postJson('/api/v1/checkout', ['payment_method' => 'online'])->json('data');
    $item = $order['groups'][0]['items'][0]['uuid'];
    $url = "/api/v1/me/orders/{$order['order_number']}/items/{$item}/files/{$file->uuid}";

    $this->get($url)->assertForbidden();

    $this->postJson("/api/v1/me/orders/{$order['order_number']}/test-payment")
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath("downloads.{$item}.0.name", 'maky.pdf');

    $this->get($url)->assertOk()->assertDownload('maky.pdf');
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
});
