<?php

namespace Modules\Education\Infrastructure\Persistence;

use Modules\Education\Application\Contracts\CourseCatalog;
use Modules\Education\Application\Contracts\CourseLine;
use Modules\Education\Domain\EnrollmentStatus;

final class EloquentCourseCatalog implements CourseCatalog
{
    public function line(string $courseUuid): ?CourseLine
    {
        return $this->lines([$courseUuid])[$courseUuid] ?? null;
    }

    public function lines(array $courseUuids): array
    {
        if ($courseUuids === []) {
            return [];
        }

        return Course::query()->whereIn('uuid', array_unique($courseUuids))->get()
            ->mapWithKeys(fn (Course $c) => [$c->uuid => new CourseLine(
                courseUuid: $c->uuid,
                makerUuid: $c->maker_profile_uuid,
                name: (string) $c->translate('name'),
                slug: $c->slug,
                imageUrl: $c->coverUrl(),
                priceMinor: $c->price_amount_minor,
                currency: $c->currency,
                isPurchasable: $c->status->isPurchasable(),
            )])
            ->all();
    }

    public function hasLifetimeAccess(string $userUuid, string $courseUuid): bool
    {
        return Enrollment::query()
            ->where('user_uuid', $userUuid)
            ->where('course_uuid', $courseUuid)
            ->whereNull('expires_at')
            ->where('status', '!=', EnrollmentStatus::Cancelled)
            ->exists();
    }
}
