<?php

namespace Modules\Marketplace\Policies;

use App\Models\User;

/** Marketplace administration (categories, product moderation). */
class CatalogAdminPolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
