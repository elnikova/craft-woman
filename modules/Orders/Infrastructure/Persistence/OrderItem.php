<?php

namespace Modules\Orders\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Immutable purchase-time snapshot (schema §7.5). */
class OrderItem extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'product_uuid', 'product_variant_uuid', 'course_uuid', 'workshop_uuid', 'product_name', 'variant_name',
        'unit_price_amount_minor', 'quantity', 'total_amount_minor', 'currency', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'quantity' => 'integer',
            'unit_price_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
        ];
    }

    /** Digital product (downloadable file) */
    public function isDigital(): bool
    {
        return ($this->metadata['type'] ?? null) === 'digital';
    }

    public function isCourse(): bool
    {
        return ($this->metadata['type'] ?? null) === 'course' || $this->course_uuid !== null;
    }

    public function isWorkshop(): bool
    {
        return ($this->metadata['type'] ?? null) === 'workshop' || $this->workshop_uuid !== null;
    }

    /** Something a Maker has to ship */
    public function isPhysical(): bool
    {
        return ! $this->isDigital() && ! $this->isCourse() && ! $this->isWorkshop();
    }
}
