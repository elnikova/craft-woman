<?php

namespace Modules\Workshops\Domain;

/**
 * What a workshop needs before review. Pure function over plain facts.
 * The meeting link is not required: makers often create it closer to the date.
 */
final class WorkshopReadiness
{
    /**
     * @param  array{name: string, description: ?string, price_minor: int, capacity: int, format: WorkshopFormat, country: ?string, city: ?string, address: ?string, sessions: int, first_start_in_future: bool, invalid_sessions: int}  $f
     * @return list<string> missing requirement keys (empty = ready)
     */
    public static function missing(array $f): array
    {
        $missing = [];
        if (mb_strlen(trim($f['name'])) < 3) {
            $missing[] = 'name';
        }
        if (mb_strlen(trim((string) $f['description'])) < 30) {
            $missing[] = 'description';
        }
        if ($f['price_minor'] <= 0) {
            $missing[] = 'price';
        }
        if ($f['capacity'] < 1) {
            $missing[] = 'capacity';
        }
        if ($f['format'] === WorkshopFormat::Offline && (! $f['country'] || ! trim((string) $f['city']) || ! trim((string) $f['address']))) {
            $missing[] = 'location';
        }
        if ($f['sessions'] < 1) {
            $missing[] = 'sessions';
        } elseif (! $f['first_start_in_future']) {
            $missing[] = 'future_sessions';
        }
        if ($f['invalid_sessions'] > 0) {
            $missing[] = 'session_times';
        }

        return $missing;
    }
}
