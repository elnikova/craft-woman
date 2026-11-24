<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Identity\Domain\MakerStatus;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

/**
 * @extends Factory<MakerProfile>
 */
class MakerProfileFactory extends Factory
{
    protected $model = MakerProfile::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'user_uuid' => User::factory()->state(['role' => Role::Master]),
            'display_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'bio' => fake()->sentence(12),
            'story' => fake()->paragraphs(2, true),
            'country_code' => 'UA',
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'follow_enabled' => true,
        ];
    }

    public function status(MakerStatus $status): static
    {
        return $this->state(fn () => [])->afterMaking(function (MakerProfile $profile) use ($status) {
            $profile->status = $status;
            $profile->approved_at = $status === MakerStatus::Approved ? now() : null;
        });
    }

    public function approved(): static
    {
        return $this->status(MakerStatus::Approved);
    }

    public function pendingReview(): static
    {
        return $this->status(MakerStatus::PendingReview);
    }
}
