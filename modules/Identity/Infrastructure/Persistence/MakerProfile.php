<?php

namespace Modules\Identity\Infrastructure\Persistence;

use App\Models\User;
use App\Support\HasTranslations;
use Database\Factories\MakerProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Identity\Domain\MakerStatus;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $display_name
 * @property string $slug
 * @property MakerStatus $status
 * @property bool $follow_enabled
 */
class MakerProfile extends Model
{
    /** @use HasFactory<MakerProfileFactory> */
    use HasFactory, HasTranslations, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected array $translatable = ['bio', 'story'];

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'user_uuid',
        'display_name',
        'content_locale',
        'slug',
        'bio',
        'story',
        'avatar_url',
        'country_code',
        'city',
        'address',
        'follow_enabled',
        'cod_enabled',
        'delivery_currency',
        'delivery_domestic_minor',
        'delivery_international_minor',
    ];

    protected $attributes = [
        'status' => 'draft',
        'follow_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => MakerStatus::class,
            'follow_enabled' => 'boolean',
            'cod_enabled' => 'boolean',
            'delivery_domestic_minor' => 'integer',
            'delivery_international_minor' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): MakerProfileFactory
    {
        return MakerProfileFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid');
    }

    /** @param Builder<self> $query */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', MakerStatus::Approved);
    }
}
