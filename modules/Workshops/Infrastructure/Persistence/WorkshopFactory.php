<?php

namespace Modules\Workshops\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;
use Modules\Workshops\Domain\WorkshopFormat;
use Modules\Workshops\Domain\WorkshopStatus;

/** @extends Factory<Workshop> */
class WorkshopFactory extends Factory
{
    protected $model = Workshop::class;

    public function definition(): array
    {
        $name = 'Майстер-клас '.Str::ucfirst($this->faker->unique()->words(2, true));

        return [
            'maker_profile_uuid' => MakerProfile::factory()->approved(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => 'Три години практики в маленькій групі: всі матеріали включено, забираєте роботу додому.',
            'price_amount_minor' => 3500,
            'currency' => 'EUR',
            'format' => WorkshopFormat::Offline,
            'timezone' => 'Europe/Kyiv',
            'capacity' => 6,
            'location_country_code' => 'UA',
            'location_city' => 'Львів',
            'location_address' => 'вул. Друкарська, 9',
        ];
    }

    /** Published, with one three-hour session a week from now. */
    public function published(): static
    {
        return $this->state(fn () => ['status' => WorkshopStatus::Published, 'published_at' => now()])
            ->afterCreating(function (Workshop $w) {
                $start = now()->addWeek()->setTime(15, 0);
                $w->sessions()->create(['starts_at' => $start, 'ends_at' => $start->copy()->addHours(3)]);
            });
    }

    public function online(): static
    {
        return $this->state(fn () => [
            'format' => WorkshopFormat::Online,
            'location_country_code' => null, 'location_city' => null, 'location_address' => null,
            'external_url' => 'https://meet.example.com/abc-defg-hij',
        ]);
    }
}
