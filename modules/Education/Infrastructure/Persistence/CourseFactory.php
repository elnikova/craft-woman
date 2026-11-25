<?php

namespace Modules\Education\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Education\Domain\AccessType;
use Modules\Education\Domain\CourseStatus;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

/** @extends Factory<Course> */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $name = 'Курс '.Str::ucfirst($this->faker->unique()->words(3, true));

        return [
            'maker_profile_uuid' => MakerProfile::factory()->approved(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => 'Покроковий курс для початківців: від підготовки матеріалів до готової роботи.',
            'price_amount_minor' => 4900,
            'currency' => 'EUR',
            'access_type' => AccessType::Lifetime,
            'certificate_enabled' => true,
        ];
    }

    /** Published course with one module of two required lessons (text + video). */
    public function published(): static
    {
        return $this->state(fn () => ['status' => CourseStatus::Published, 'published_at' => now()])
            ->afterCreating(function (Course $course) {
                $module = $course->modules()->create(['title' => 'Основи', 'sort_order' => 1]);
                $intro = $module->lessons()->create(['title' => 'Знайомство', 'sort_order' => 1, 'is_preview' => true]);
                $intro->materials()->create(['type' => 'text', 'content' => 'Вітаю на курсі!', 'sort_order' => 1]);
                $video = $module->lessons()->create(['title' => 'Перші петлі', 'sort_order' => 2]);
                $video->materials()->create(['type' => 'video', 'external_url' => 'https://youtu.be/dQw4w9WgXcQ', 'sort_order' => 1]);
            });
    }

    public function limited(int $days): static
    {
        return $this->state(fn () => ['access_type' => AccessType::Limited, 'access_days' => $days]);
    }
}
