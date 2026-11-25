<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Marketplace\Infrastructure\Persistence\Category;
use Modules\Workshops\Domain\WorkshopFormat;
use Modules\Workshops\Domain\WorkshopStatus;
use Modules\Workshops\Infrastructure\Persistence\Workshop;

/** Three upcoming demo workshops: two in person, one online over two evenings. */
class WorkshopSeeder extends Seeder
{
    public function run(): void
    {
        if (Workshop::query()->exists()) {
            return;
        }

        $demo = [
            [
                'email' => 'marko@craftwoman.local', 'category' => 'Кераміка', 'price' => 4500, 'capacity' => 6, 'format' => WorkshopFormat::Offline,
                'city' => 'Опішня', 'address' => 'вул. Гончарна, 12, майстерня у дворі',
                'name' => 'Гончарний круг: перша чашка', 'en' => 'Pottery wheel: your first mug',
                'description' => "Три години за кругом у маленькій групі. Центруємо глину, витягуємо стінки, формуємо ручку.\n\nГлина, фартухи й випал включені — готову чашку заберете через два тижні.",
                'note' => 'Одягніть те, що не шкода забруднити. Нігті краще коротко.',
                'sessions' => [[10, '11:00', '14:00']],
            ],
            [
                'email' => 'oksana@craftwoman.local', 'category' => 'В’язання', 'price' => 2500, 'capacity' => 12, 'format' => WorkshopFormat::Online,
                'city' => null, 'address' => null,
                'name' => 'Шкарпетки на пʼяти спицях: два вечори', 'en' => 'Socks on five needles: two evenings',
                'description' => 'Онлайн у Zoom. Перший вечір — манжета й пʼятка, другий — стопа й мисок. Потрібні вміння набирати петлі й вʼязати лицьову гладь.',
                'note' => 'Підготуйте 100 г шкарпеткової пряжі та панчішні спиці 2,5 мм.',
                'url' => 'https://zoom.us/j/1234567890',
                'sessions' => [[14, '19:00', '21:00'], [16, '19:00', '21:00']],
            ],
            [
                'email' => 'iryna@craftwoman.local', 'category' => 'Вишивка', 'price' => 3000, 'capacity' => 8, 'format' => WorkshopFormat::Offline,
                'city' => 'Полтава', 'address' => 'вул. Соборності, 7, кав’ярня «Нитка», 2 поверх',
                'name' => 'Вишита брошка за один день', 'en' => null,
                'description' => 'Вишиваємо гладдю невелику брошку з квітковим мотивом і оформлюємо її на фетр. Підійде новачкам.',
                'note' => null,
                'sessions' => [[21, '12:00', '16:00']],
            ],
        ];

        foreach ($demo as $d) {
            $maker = MakerProfile::query()->whereHas('user', fn ($q) => $q->where('email', $d['email']))->first();
            if (! $maker) {
                continue;
            }
            $w = new Workshop([
                'maker_profile_uuid' => $maker->uuid,
                'name' => $d['name'],
                'slug' => Str::slug($d['name']),
                'description' => $d['description'],
                'price_amount_minor' => $d['price'],
                'currency' => 'EUR',
                'format' => $d['format'],
                'timezone' => 'Europe/Kyiv',
                'capacity' => $d['capacity'],
                'location_country_code' => 'UA',
                'location_city' => $d['city'],
                'location_address' => $d['address'],
                'external_url' => $d['url'] ?? null,
                'participant_note' => $d['note'],
            ]);
            $w->status = WorkshopStatus::Published;
            $w->published_at = now();
            $w->translations = $d['en'] ? ['en' => ['name' => $d['en']]] : null;
            $w->save();

            foreach ($d['sessions'] as [$days, $from, $to]) {
                $date = now('Europe/Kyiv')->addDays($days)->format('Y-m-d');
                $w->sessions()->create([
                    'starts_at' => \Carbon\CarbonImmutable::parse("{$date} {$from}", 'Europe/Kyiv')->utc(),
                    'ends_at' => \Carbon\CarbonImmutable::parse("{$date} {$to}", 'Europe/Kyiv')->utc(),
                ]);
            }

            $category = Category::query()->where('name', $d['category'])->first();
            if ($category) {
                $w->categories()->attach($category->uuid, ['created_at' => now()]);
            }
        }
    }
}
