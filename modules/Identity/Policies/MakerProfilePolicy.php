<?php

namespace Modules\Identity\Policies;

use App\Models\User;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

class MakerProfilePolicy
{
    /** Approved profiles are public; others are visible to their owner and to admins. */
    public function view(?User $user, MakerProfile $profile): bool
    {
        if ($profile->status->isActive()) {
            return true;
        }

        return $user !== null && ($user->isAdmin() || $user->uuid === $profile->user_uuid);
    }

    /** A Maker may manage only their own profile (domain-rules.md §4). */
    public function update(User $user, MakerProfile $profile): bool
    {
        return $user->uuid === $profile->user_uuid;
    }

    /** Approve, reject, suspend, reinstate. */
    public function review(User $user, ?MakerProfile $profile = null): bool
    {
        return $user->isAdmin();
    }
}
