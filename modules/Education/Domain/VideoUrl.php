<?php

namespace Modules\Education\Domain;

/**
 * Accepts YouTube and Vimeo links in their common shapes and turns them into
 * privacy-friendly embed URLs. Anything else is rejected.
 */
final class VideoUrl
{
    /** @return array{provider: string, id: string, embed: string}|null */
    public static function parse(string $url): ?array
    {
        $url = trim($url);
        $patterns = [
            'youtube' => '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~',
            'vimeo' => '~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{6,12})(?:/([0-9a-f]{6,20}))?~',
        ];

        foreach ($patterns as $provider => $pattern) {
            if (preg_match($pattern, $url, $m)) {
                $embed = $provider === 'youtube'
                    ? "https://www.youtube-nocookie.com/embed/{$m[1]}"
                    : "https://player.vimeo.com/video/{$m[1]}".(isset($m[2]) && $m[2] !== '' ? "?h={$m[2]}" : '');

                return ['provider' => $provider, 'id' => $m[1], 'embed' => $embed];
            }
        }

        return null;
    }
}
