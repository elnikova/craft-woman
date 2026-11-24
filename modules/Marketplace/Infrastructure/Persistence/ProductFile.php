<?php

namespace Modules\Marketplace\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Downloadable file of a digital product, stored on a private disk (schema §4.4). */
class ProductFile extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['file_name', 'storage_disk', 'storage_path', 'mime_type', 'file_size', 'sort_order'];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'sort_order' => 'integer'];
    }
}
