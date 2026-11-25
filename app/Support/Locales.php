<?php

namespace App\Support;

/** Languages the platform speaks (product-requirements.md §16). Adding one is a config change, not a schema change. */
final class Locales
{
    public const DEFAULT = 'uk';

    /** @var list<string> */
    public const SUPPORTED = ['uk', 'en'];

    public static function isSupported(?string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }

    public static function current(): string
    {
        $locale = app()->getLocale();

        return self::isSupported($locale) ? $locale : self::DEFAULT;
    }
}
