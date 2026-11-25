<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Education\Domain\AccessType;
use Modules\Education\Domain\CourseStatus;
use Modules\Education\Infrastructure\Persistence\Course;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Marketplace\Infrastructure\Persistence\Category;

/** Two demo courses with a short curriculum, one translated into English. */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        if (Course::query()->exists()) {
            return;
        }

        $demo = [
            [
                'email' => 'oksana@craftwoman.local', 'category' => 'В’язання', 'price' => 4900, 'access' => AccessType::Lifetime, 'days' => null,
                'name' => 'Вʼязання спицями з нуля', 'en' => 'Knitting from scratch',
                'description' => "Від вибору пряжі до першого шарфа. Покроково, з відео та схемами.\n\nПідійде, якщо ви ніколи не тримали спиці.",
                'en_description' => "From choosing yarn to your first scarf. Step by step, with videos and patterns.\n\nGood if you have never held needles before.",
                'modules' => [
                    ['Підготовка', [['Пряжа і спиці', 'text', true], ['Набір петель', 'video', false]]],
                    ['Перший шарф', [['Лицьова гладь', 'video', false], ['Закриття петель', 'video', false], ['Схема шарфа', 'link', false]]],
                ],
            ],
            [
                'email' => 'iryna@craftwoman.local', 'category' => 'Вишивка', 'price' => 3500, 'access' => AccessType::Limited, 'days' => 180,
                'name' => 'Полтавська вишивка: основні шви', 'en' => null,
                'description' => 'Білим по білому: виколювання, лиштва, набирування. Для тих, хто вже знає хрестик і хоче глибше.',
                'en_description' => null,
                'modules' => [
                    ['Матеріали й підготовка', [['Полотно і нитки', 'text', true]]],
                    ['Шви', [['Виколювання', 'video', false], ['Лиштва', 'video', false]]],
                ],
            ],
        ];

        foreach ($demo as $d) {
            $maker = MakerProfile::query()->whereHas('user', fn ($q) => $q->where('email', $d['email']))->first();
            if (! $maker) {
                continue;
            }
            $course = new Course([
                'maker_profile_uuid' => $maker->uuid,
                'name' => $d['name'],
                'slug' => Str::slug($d['name']),
                'description' => $d['description'],
                'price_amount_minor' => $d['price'],
                'currency' => 'EUR',
                'access_type' => $d['access'],
                'access_days' => $d['days'],
                'certificate_enabled' => true,
            ]);
            if ($d['en']) {
                $course->translations = ['en' => ['name' => $d['en'], 'description' => $d['en_description']]];
            }
            $course->status = CourseStatus::Published;
            $course->published_at = now()->subDays(random_int(1, 10));
            $course->save();

            if ($category = Category::query()->where('name', $d['category'])->first()) {
                $course->categories()->attach($category->uuid, ['created_at' => now()]);
            }

            foreach ($d['modules'] as $mi => [$title, $lessons]) {
                $module = $course->modules()->create(['title' => $title, 'sort_order' => $mi + 1]);
                foreach ($lessons as $li => [$lessonTitle, $type, $preview]) {
                    $lesson = $module->lessons()->create(['title' => $lessonTitle, 'sort_order' => $li + 1, 'is_preview' => $preview]);
                    $lesson->materials()->create(match ($type) {
                        'text' => ['type' => 'text', 'content' => "{$lessonTitle}.\n\nУ цьому уроці розбираємо основи й готуємося до практики.", 'sort_order' => 1],
                        'video' => ['type' => 'video', 'title' => $lessonTitle, 'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'sort_order' => 1],
                        'link' => ['type' => 'link', 'title' => 'Схема (PDF)', 'external_url' => 'https://example.com/scheme.pdf', 'sort_order' => 1],
                    });
                }
            }
        }
    }
}
