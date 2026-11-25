<?php

namespace Modules\Identity\Infrastructure\Persistence;

use Modules\Identity\Application\Contracts\MakerDirectory;
use Modules\Identity\Application\Contracts\MakerSummary;

final class EloquentMakerDirectory implements MakerDirectory
{
    public function forUser(string $userUuid): ?MakerSummary
    {
        $profile = MakerProfile::query()->where('user_uuid', $userUuid)->first();

        return $profile ? $this->toSummary($profile) : null;
    }

    public function findBySlug(string $slug): ?MakerSummary
    {
        $profile = MakerProfile::query()->where('slug', $slug)->first();

        return $profile ? $this->toSummary($profile) : null;
    }

    public function approvedMakerUuidsQuery(): \Illuminate\Database\Query\Builder
    {
        return MakerProfile::query()->approved()->select('uuid')->toBase();
    }

    public function summaries(array $makerUuids): array
    {
        if ($makerUuids === []) {
            return [];
        }

        return MakerProfile::query()
            ->whereIn('uuid', array_unique($makerUuids))
            ->get()
            ->mapWithKeys(fn (MakerProfile $p) => [$p->uuid => $this->toSummary($p)])
            ->all();
    }

    private function toSummary(MakerProfile $p): MakerSummary
    {
        return new MakerSummary(
            uuid: $p->uuid,
            userUuid: $p->user_uuid,
            displayName: $p->display_name,
            slug: $p->slug,
            city: $p->city,
            avatarUrl: $p->avatar_url,
            isApproved: $p->status->isActive(),
            countryCode: $p->country_code,
            codEnabled: $p->cod_enabled,
            deliveryCurrency: $p->delivery_currency,
            deliveryDomesticMinor: $p->delivery_domestic_minor,
            deliveryInternationalMinor: $p->delivery_international_minor,
        );
    }
}
