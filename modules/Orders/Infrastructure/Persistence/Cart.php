<?php

namespace Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A user's cart, or a guest cart identified by its UUID (sent as X-Cart-Token). */
class Cart extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['user_uuid', 'currency'];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_uuid')->orderBy('created_at');
    }
}
