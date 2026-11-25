<?php

namespace Modules\Identity\Application\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Domain\Exceptions\AccountRuleViolation;
use Modules\Identity\Domain\UserStatus;

/** Administrators managing other people's accounts. */
class UserAdminService
{
    public function __construct(private readonly AccountService $accounts) {}

    public function block(User $admin, User $user, string $reason): User
    {
        $this->guardOthers($admin, $user);
        if ($user->status === UserStatus::Blocked) {
            throw AccountRuleViolation::alreadyInState('blocked');
        }
        if ($user->isAdmin()) {
            $this->guardLastAdmin($user);
        }

        return DB::transaction(function () use ($user, $reason) {
            $user->forceFill([
                'status' => UserStatus::Blocked,
                'blocked_at' => now(),
                'blocked_reason' => $reason,
                'remember_token' => null,
            ])->save();

            // Sign them out everywhere right away
            $this->accounts->dropSessions($user);
            $user->tokens()->delete();

            return $user;
        });
    }

    public function unblock(User $admin, User $user): User
    {
        $this->guardOthers($admin, $user);
        if ($user->status === UserStatus::Active) {
            throw AccountRuleViolation::alreadyInState('active');
        }

        $user->forceFill(['status' => UserStatus::Active, 'blocked_at' => null, 'blocked_reason' => null])->save();

        return $user;
    }

    /** Only buyer ⇄ admin: makers get their role through the maker profile flow. */
    public function setRole(User $admin, User $user, Role $role): User
    {
        $this->guardOthers($admin, $user);
        if ($user->role === Role::Master || $role === Role::Master) {
            throw AccountRuleViolation::makerRole();
        }
        if ($user->isAdmin() && $role !== Role::Admin) {
            $this->guardLastAdmin($user);
        }

        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function guardOthers(User $admin, User $user): void
    {
        if ($admin->is($user)) {
            throw AccountRuleViolation::notOnYourself();
        }
    }

    private function guardLastAdmin(User $user): void
    {
        $others = User::query()
            ->where('role', Role::Admin)
            ->where('status', UserStatus::Active)
            ->whereKeyNot($user->uuid)
            ->exists();

        if (! $others) {
            throw AccountRuleViolation::lastAdmin();
        }
    }
}
