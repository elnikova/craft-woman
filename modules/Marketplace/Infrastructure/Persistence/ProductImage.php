<?php

namespace Modules\Marketplace\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Image reference; the binary lives on the public disk (schema §4.3). */
class ProductImage extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['file_url', 'storage_path', 'alt_text', 'sort_order'];
}
