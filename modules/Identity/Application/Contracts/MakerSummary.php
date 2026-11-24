<?php

namespace Modules\Identity\Application\Contracts;

/** Read model other modules use to show who made something. */
final readonly class MakerSummary
{
    public function __construct(
        public string $uuid,
        public string $userUuid,
        public string $displayName,
        public string $slug,
        public ?string $city,
        public ?string $avatarUrl,
        public bool $isApproved,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'display_name' => $this->displayName,
            'slug' => $this->slug,
            'city' => $this->city,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}
