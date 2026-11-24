<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'gender',
        'subscription_uuid',
        'oauth_provider',
        'oauth_provider_id',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_uuid');
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function makerProfile(): HasOne
    {
        return $this->hasOne(MakerProfile::class, 'user_uuid');
    }

    /** Full Maker capabilities require an approved Maker profile (domain-rules.md §3). */
    public function isApprovedMaker(): bool
    {
        return $this->makerProfile?->status->isActive() ?? false;
    }

    public function isMaster(): bool
    {
        return $this->role === Role::Master;
    }
}
