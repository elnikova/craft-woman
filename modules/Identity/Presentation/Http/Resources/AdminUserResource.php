<?php

namespace Modules\Identity\Presentation\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'blocked_at' => $this->blocked_at,
            'blocked_reason' => $this->blocked_reason,
            'maker' => $this->makerProfile ? [
                'uuid' => $this->makerProfile->uuid,
                'display_name' => $this->makerProfile->display_name,
                'slug' => $this->makerProfile->slug,
                'status' => $this->makerProfile->status->value,
            ] : null,
            'is_you' => $request->user()?->is($this->resource) ?? false,
            'created_at' => $this->created_at,
        ];
    }
}
