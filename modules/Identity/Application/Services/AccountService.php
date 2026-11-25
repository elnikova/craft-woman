<?php

namespace Modules\Identity\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The signed-in user's own account settings. */
class AccountService
{
    /** @param array{name: string, email: string} $data */
    public function updateProfile(User $user, array $data): User
    {
        $emailChanged = mb_strtolower($data['email']) !== mb_strtolower($user->email);

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if ($emailChanged) {
            // New address is unconfirmed until verification e-mails exist
            $user->email_verified_at = null;
        }
        $user->save();

        return $user;
    }

    /** Changes the password and signs the user out everywhere except the current session. */
    public function changePassword(User $user, string $password, ?string $currentSessionId): void
    {
        $user->forceFill(['password' => $password])->setRememberToken(null);
        $user->save();

        $this->dropSessions($user, except: $currentSessionId);
        $user->tokens()->delete();
    }

    public function dropSessions(User $user, ?string $except = null): void
    {
        DB::table('sessions')
            ->where('user_id', $user->uuid)
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->delete();
    }
}
