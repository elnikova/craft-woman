<?php

namespace Modules\Identity\Application\DTOs;

/** Editable Maker profile fields, as submitted by the Maker. */
final readonly class MakerProfileData
{
    public function __construct(
        public string $displayName,
        public ?string $bio = null,
        public ?string $story = null,
        public ?string $avatarUrl = null,
        public ?string $countryCode = null,
        public ?string $city = null,
        public ?string $address = null,
        public bool $followEnabled = true,
        public bool $codEnabled = false,
        public string $deliveryCurrency = 'EUR',
        public int $deliveryDomesticMinor = 0,
        public ?int $deliveryInternationalMinor = null,
        /** null = keep the current value */
        public ?string $contentLocale = null,
        /** @var array<string, array<string, ?string>>|null null = keep the current translations */
        public ?array $translations = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            displayName: (string) $data['display_name'],
            bio: $data['bio'] ?? null,
            story: $data['story'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            countryCode: isset($data['country_code']) ? strtoupper((string) $data['country_code']) : null,
            city: $data['city'] ?? null,
            address: $data['address'] ?? null,
            followEnabled: (bool) ($data['follow_enabled'] ?? true),
            codEnabled: (bool) ($data['cod_enabled'] ?? false),
            deliveryCurrency: (string) ($data['delivery_currency'] ?? 'EUR'),
            deliveryDomesticMinor: (int) ($data['delivery_domestic_minor'] ?? 0),
            deliveryInternationalMinor: isset($data['delivery_international_minor']) ? (int) $data['delivery_international_minor'] : null,
            contentLocale: $data['content_locale'] ?? null,
            translations: array_key_exists('translations', $data) ? ($data['translations'] ?? []) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'display_name' => $this->displayName,
            'bio' => $this->bio,
            'story' => $this->story,
            'avatar_url' => $this->avatarUrl,
            'country_code' => $this->countryCode,
            'city' => $this->city,
            'address' => $this->address,
            'follow_enabled' => $this->followEnabled,
            'cod_enabled' => $this->codEnabled,
            'delivery_currency' => $this->deliveryCurrency,
            'delivery_domestic_minor' => $this->deliveryDomesticMinor,
            'delivery_international_minor' => $this->deliveryInternationalMinor,
            ...($this->contentLocale !== null ? ['content_locale' => $this->contentLocale] : []),
        ];
    }
}
