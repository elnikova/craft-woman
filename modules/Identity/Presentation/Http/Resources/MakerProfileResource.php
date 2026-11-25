<?php

namespace Modules\Identity\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Infrastructure\Persistence\MakerProfile;

/**
 * Public representation by default. Private fields (address, status, review note)
 * are added only for the owner and admins.
 *
 * @mixin MakerProfile
 */
class MakerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $private = $user !== null && ($user->isAdmin() || $user->uuid === $this->user_uuid);

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'story' => $this->story,
            'avatar_url' => $this->avatar_url,
            'country_code' => $this->country_code,
            'city' => $this->city,
            'follow_enabled' => $this->follow_enabled,
            'checkout' => [
                'cod_enabled' => $this->cod_enabled,
                'delivery_currency' => $this->delivery_currency,
                'delivery_domestic_minor' => $this->delivery_domestic_minor,
                'delivery_international_minor' => $this->delivery_international_minor,
            ],
            'approved_at' => $this->approved_at,
            $this->mergeWhen($private, fn () => [
                'address' => $this->address,
                'status' => $this->status->value,
                'review_note' => $this->review_note,
                'is_editable' => $this->status->isEditable(),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ]),
            $this->mergeWhen($user?->isAdmin() ?? false, fn () => [
                'owner' => [
                    'uuid' => $this->user->uuid,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ]),
        ];
    }
}
