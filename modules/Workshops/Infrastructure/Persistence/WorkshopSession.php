<?php

namespace Modules\Workshops\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One meeting of a workshop. Times are stored in UTC and shown in the workshop's timezone. */
class WorkshopSession extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = ['starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Workshop, $this> */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_uuid');
    }
}
