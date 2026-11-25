<?php

namespace Modules\Workshops\Infrastructure\Persistence;

use Modules\Workshops\Application\Contracts\WorkshopCatalog;
use Modules\Workshops\Application\Contracts\WorkshopLine;
use Modules\Workshops\Application\Services\BookingService;
use Modules\Workshops\Domain\BookingStatus;

final class EloquentWorkshopCatalog implements WorkshopCatalog
{
    public function __construct(private readonly BookingService $bookings) {}

    public function line(string $workshopUuid): ?WorkshopLine
    {
        return $this->lines([$workshopUuid])[$workshopUuid] ?? null;
    }

    public function lines(array $workshopUuids): array
    {
        if ($workshopUuids === []) {
            return [];
        }

        return Workshop::query()
            ->whereIn('uuid', array_unique($workshopUuids))
            ->with('sessions')
            ->withSum(['bookings as seats_taken' => fn ($q) => $q->whereIn('status', BookingStatus::holding())], 'seats')
            ->get()
            ->mapWithKeys(fn (Workshop $w) => [$w->uuid => new WorkshopLine(
                workshopUuid: $w->uuid,
                makerUuid: $w->maker_profile_uuid,
                name: (string) $w->translate('name'),
                slug: $w->slug,
                imageUrl: $w->coverUrl(),
                priceMinor: $w->price_amount_minor,
                currency: $w->currency,
                isPurchasable: $w->isBookable(),
                seatsLeft: max(0, $w->capacity - (int) $w->seats_taken),
                startsAt: $w->startsAt()?->setTimezone($w->timezone)->toIso8601String(),
            )])
            ->all();
    }

    public function reserve(string $workshopUuid, string $userUuid, string $orderItemUuid, int $seats): void
    {
        $this->bookings->reserve($workshopUuid, $userUuid, $orderItemUuid, $seats);
    }

    public function release(array $orderItemUuids, string $reason): void
    {
        $this->bookings->release($orderItemUuids, $reason);
    }
}
