<?php

namespace App\Support;

/**
 * User-written content in one source language (`content_locale`) plus optional
 * translations stored as JSON: {"en": {"name": "…", "description": "…"}}.
 * A missing translation falls back to the source text, and callers are told
 * which language they actually got so the UI can mark it.
 *
 * Models list their translatable fields in `$translatable`.
 *
 * @property string $content_locale
 * @property array<string, array<string, string|null>>|null $translations
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
    }

    /** @return list<string> */
    public function translatableFields(): array
    {
        return $this->translatable ?? [];
    }

    public function sourceLocale(): string
    {
        return $this->content_locale ?: Locales::DEFAULT;
    }

    public function hasTranslation(string $locale): bool
    {
        if ($locale === $this->sourceLocale()) {
            return true;
        }
        $first = $this->translatableFields()[0] ?? null;

        return $first !== null && filled($this->translations[$locale][$first] ?? null);
    }

    public function translate(string $field, ?string $locale = null): mixed
    {
        $locale ??= Locales::current();
        if ($locale !== $this->sourceLocale()) {
            $value = $this->translations[$locale][$field] ?? null;
            if (filled($value)) {
                return $value;
            }
        }

        return $this->getAttribute($field);
    }

    /**
     * Translatable fields in the requested language, plus `content_locale`: the
     * language of what was returned (source language when there is no translation).
     *
     * @return array<string, mixed>
     */
    public function localized(?string $locale = null): array
    {
        $locale ??= Locales::current();
        $translated = $this->hasTranslation($locale);
        $values = [];
        foreach ($this->translatableFields() as $field) {
            $values[$field] = $translated ? $this->translate($field, $locale) : $this->getAttribute($field);
        }

        return [...$values, 'content_locale' => $translated ? $locale : $this->sourceLocale()];
    }

    /**
     * Keep only supported, non-source locales and known fields; drop empty values.
     *
     * @param  array<string, array<string, mixed>>|null  $translations
     * @return array<string, array<string, string>>
     */
    public function cleanTranslations(?array $translations, ?string $sourceLocale = null): array
    {
        $sourceLocale ??= $this->sourceLocale();
        $clean = [];
        foreach ($translations ?? [] as $locale => $fields) {
            if (! Locales::isSupported($locale) || $locale === $sourceLocale || ! is_array($fields)) {
                continue;
            }
            foreach ($this->translatableFields() as $field) {
                $value = isset($fields[$field]) ? trim((string) $fields[$field]) : '';
                if ($value !== '') {
                    $clean[$locale][$field] = $value;
                }
            }
        }

        return $clean;
    }
}
