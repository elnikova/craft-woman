<?php

namespace Modules\Workshops\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Workshops\Domain\BookingStatus;
use Modules\Workshops\Domain\Seats;
use Modules\Workshops\Infrastructure\Persistence\Workshop;
use Modules\Workshops\Infrastructure\Persistence\WorkshopBooking;

/** Seats: held at checkout, confirmed on payment, released on cancellation. */
final class BookingService
{
    /** Concurrency-safe: the workshop row is locked while seats are counted (domain-rules: capacity never exceeded). */
    public function reserve(string $workshopUuid, string $userUuid, string $orderItemUuid, int $seats): WorkshopBooking
    {
        return DB::transaction(function () use ($workshopUuid, $userUuid, $orderItemUuid, $seats) {
            /** @var Workshop $workshop */
            $workshop = Workshop::query()->lockForUpdate()->findOrFail($workshopUuid);
            Seats::assertAvailable($workshop->capacity, $workshop->seatsTaken(), $seats);

            return $workshop->bookings()->create([
                'user_uuid' => $userUuid,
                'order_item_uuid' => $orderItemUuid,
                'seats' => $seats,
                'status' => BookingStatus::Pending,
                'booked_at' => now(),
            ]);
        });
    }

    /** @param list<string> $orderItemUuids */
    public function confirm(array $orderItemUuids): void
    {
        if ($orderItemUuids === []) {
            return;
        }
        WorkshopBooking::query()
            ->whereIn('order_item_uuid', $orderItemUuids)
            ->where('status', BookingStatus::Pending)
            ->update(['status' => BookingStatus::Confirmed, 'confirmed_at' => now()]);
    }

    /** @param list<string> $orderItemUuids */
    public function release(array $orderItemUuids, string $reason): void
    {
        if ($orderItemUuids === []) {
            return;
        }
        WorkshopBooking::query()
            ->whereIn('order_item_uuid', $orderItemUuids)
            ->whereIn('status', BookingStatus::holding())
            ->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => mb_substr($reason, 0, 500)]);
    }

    /** Confirmed bookings of a user, newest workshop first. Pending ones live on the unpaid order. */
    public function forUser(string $userUuid): Collection
    {
        return WorkshopBooking::query()
            ->where('user_uuid', $userUuid)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Cancelled])
            ->whereNotNull('confirmed_at')
            ->with('workshop.sessions')
            ->latest('booked_at')
            ->get();
    }

    public function hasConfirmed(string $userUuid, Workshop $workshop): bool
    {
        return $workshop->bookings()->where('user_uuid', $userUuid)->where('status', BookingStatus::Confirmed)->exists();
    }

    /** Seats the user holds (confirmed) — shown on the workshop page */
    public function seatsOf(string $userUuid, Workshop $workshop): int
    {
        return (int) $workshop->bookings()->where('user_uuid', $userUuid)->where('status', BookingStatus::Confirmed)->sum('seats');
    }
}
