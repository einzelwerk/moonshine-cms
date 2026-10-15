<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Support;

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;

/**
 * The caption of a record for the language of the panel, read out of a
 * dictionary of languages. A model of the application may already hand the
 * caption over as a string — a translatable trait does that — and then the
 * string is the answer.
 */
final class Translated
{
    public static function caption(mixed $value, ?string $locale = null): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (! is_array($value) || $value === []) {
            return '';
        }

        $container = Container::getInstance();
        $locale ??= $container->make(Application::class)->getLocale();
        $default = $container->bound(LanguageCatalog::class)
            ? $container->make(LanguageCatalog::class)->defaultLocale()
            : null;

        foreach ([$locale, $default] as $candidate) {
            $translation = $candidate === null ? null : ($value[$candidate] ?? null);

            if (is_string($translation) && $translation !== '') {
                return $translation;
            }
        }

        foreach ($value as $translation) {
            if (is_string($translation) && $translation !== '') {
                return $translation;
            }
        }

        return '';
    }
}
