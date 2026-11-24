<?php

namespace Modules\Marketplace\Policies;

use App\Models\User;
use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Marketplace\Infrastructure\Persistence\Product;

class ProductPolicy
{
    public function __construct(private readonly MakerDirectory $makers) {}

    /** Published products are public; others are visible to their Maker and to admins. */
    public function view(?User $user, Product $product): bool
    {
        if ($product->status->isPurchasable()) {
            return true;
        }

        return $user !== null && ($user->isAdmin() || $this->owns($user, $product));
    }

    /** A Maker may manage only their own products (domain-rules.md §4). */
    public function update(User $user, Product $product): bool
    {
        return $this->owns($user, $product);
    }

    private function owns(User $user, Product $product): bool
    {
        return $this->makers->forUser($user->uuid)?->uuid === $product->maker_profile_uuid;
    }
}
