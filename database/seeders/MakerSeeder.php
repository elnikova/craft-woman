<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Application\DTOs\MakerProfileData;
use Modules\Identity\Application\Services\MakerProfileService;

/** Demo makers for local development: four approved and one waiting for review. */
class MakerSeeder extends Seeder
{
    public function run(MakerProfileService $makers): void
    {
        $demo = [
            ['Оксана Ткачук', 'oksana@craftwoman.local', 'Львів', 'Пледи й светри з карпатської вовни, вʼязані вручну.', true],
            ['Марко Зайченко', 'marko@craftwoman.local', 'Опішня', 'Димлена кераміка: чашки, тарілки, глечики.', true],
            ['Ірина Бойко', 'iryna@craftwoman.local', 'Полтава', 'Вишивка гладдю й хрестиком, схеми та майстер-класи.', true],
            ['Лука Мельник', 'luka@craftwoman.local', 'Київ', 'Гаманці та ремені з рослинного дублення, ручний шов.', true],
            ['Софія Коваль', 'sofia@craftwoman.local', 'Одеса', 'Свічки з соєвого воску та бджолиного.', false],
        ];

        foreach ($demo as [$name, $email, $city, $bio, $approve]) {
            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => Role::Master,
            ]);

            $profile = $makers->createDraft($user, new MakerProfileData(
                displayName: $name,
                bio: $bio,
                countryCode: 'UA',
                city: $city,
                codEnabled: $email !== 'luka@craftwoman.local', // один майстер — тільки передоплата
                deliveryCurrency: 'EUR',
                deliveryDomesticMinor: 400,
                deliveryInternationalMinor: $email === 'iryna@craftwoman.local' ? null : 1500,
            ));
            $makers->submitForReview($profile);

            if ($approve) {
                $makers->approve($profile);
            }
        }
    }
}
