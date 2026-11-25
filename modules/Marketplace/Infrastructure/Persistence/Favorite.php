<?php

namespace Modules\Marketplace\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product the user saved (postgresql-schema.md §4.9). Only products for now;
 * makers, courses and workshops can become further targets later.
 */
class Favorite extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $primaryKey = 'uuid';

    protected $fillable = ['user_uuid', 'product_uuid'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }
}
