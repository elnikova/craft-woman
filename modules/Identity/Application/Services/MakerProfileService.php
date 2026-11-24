<?php

namespace Modules\Identity\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Application\DTOs\MakerProfileData;
use Modules\Identity\Domain\Exceptions\MakerProfileAlreadyExists;
use Modules\Identity\Domain\Exceptions\MakerProfileNotEditable;
use Modules\Identity\Domain\MakerStatus;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

/**
 * Application service for the Maker profile lifecycle.
 * Controllers call this; status rules live in the MakerStatus domain enum.
 */
final class MakerProfileService
{
    public function createDraft(User $user, MakerProfileData $data): MakerProfile
    {
        if (MakerProfile::withTrashed()->where('user_uuid', $user->uuid)->exists()) {
            throw MakerProfileAlreadyExists::forUser($user->uuid);
        }

        $profile = new MakerProfile([
            ...$data->toAttributes(),
            'user_uuid' => $user->uuid,
            'slug' => $this->uniqueSlug($data->displayName),
        ]);
        $profile->status = MakerStatus::Draft;
        $profile->save();

        return $profile;
    }

    public function update(MakerProfile $profile, MakerProfileData $data): MakerProfile
    {
        if (! $profile->status->isEditable()) {
            throw MakerProfileNotEditable::inStatus($profile->status);
        }

        $profile->fill($data->toAttributes());

        // The slug follows the name only until the profile is public — public links must not break.
        if ($profile->isDirty('display_name') && ! $profile->status->isActive()) {
            $profile->slug = $this->uniqueSlug($data->displayName, ignore: $profile->uuid);
        }

        $profile->save();

        return $profile;
    }

    /** Create the profile or update the existing one (PUT /me/maker-profile). */
    public function upsert(User $user, MakerProfileData $data): MakerProfile
    {
        $profile = MakerProfile::query()->where('user_uuid', $user->uuid)->first();

        return $profile ? $this->update($profile, $data) : $this->createDraft($user, $data);
    }

    public function submitForReview(MakerProfile $profile): MakerProfile
    {
        return $this->transition($profile, MakerStatus::PendingReview, note: null);
    }

    public function approve(MakerProfile $profile): MakerProfile
    {
        return DB::transaction(function () use ($profile) {
            $this->transition($profile, MakerStatus::Approved, note: null, save: false);
            $profile->approved_at ??= now();
            $profile->save();

            return $profile;
        });
    }

    public function reject(MakerProfile $profile, string $note): MakerProfile
    {
        return $this->transition($profile, MakerStatus::Rejected, $note);
    }

    public function suspend(MakerProfile $profile, string $note): MakerProfile
    {
        return $this->transition($profile, MakerStatus::Suspended, $note);
    }

    public function reinstate(MakerProfile $profile): MakerProfile
    {
        return $this->transition($profile, MakerStatus::Approved, note: null);
    }

    private function transition(MakerProfile $profile, MakerStatus $next, ?string $note, bool $save = true): MakerProfile
    {
        $profile->status = $profile->status->transitionTo($next);
        $profile->review_note = $note;

        if ($save) {
            $profile->save();
        }

        return $profile;
    }

    private function uniqueSlug(string $name, ?string $ignore = null): string
    {
        $base = Str::slug($name) ?: 'maker';
        $slug = $base;
        $i = 2;

        while (MakerProfile::withTrashed()
            ->where('slug', $slug)
            ->when($ignore, fn ($q) => $q->where('uuid', '!=', $ignore))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
