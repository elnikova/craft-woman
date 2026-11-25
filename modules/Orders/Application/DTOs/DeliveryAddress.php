<?php

namespace Modules\Orders\Application\DTOs;

final readonly class DeliveryAddress
{
    public function __construct(
        public string $recipientName,
        public string $phone,
        public string $countryCode,
        public string $city,
        public string $address,
        public ?string $postalCode,
    ) {}

    /** @param array<string, mixed> $v */
    public static function fromArray(array $v): self
    {
        return new self(
            recipientName: trim((string) $v['recipient_name']),
            phone: trim((string) $v['phone']),
            countryCode: strtoupper((string) $v['country_code']),
            city: trim((string) $v['city']),
            address: trim((string) $v['address']),
            postalCode: isset($v['postal_code']) ? trim((string) $v['postal_code']) : null,
        );
    }

    /** @return array<string, ?string> */
    public function toArray(): array
    {
        return [
            'recipient_name' => $this->recipientName,
            'phone' => $this->phone,
            'country_code' => $this->countryCode,
            'city' => $this->city,
            'address' => $this->address,
            'postal_code' => $this->postalCode,
        ];
    }
}
