<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Links;

use Illuminate\Container\Container;
use Throwable;

/**
 * One stored caption as one line for a select.
 *
 * A name in the CMS is either a plain string or a dictionary keyed by language,
 * and a select shows the language of the panel with a fallback to any filled
 * one: an editor must recognise the record even when the translation he is
 * working in is still empty.
 */
final readonly class Caption
{
    /**
     * `null` for a value that carries no caption at all, so the caller can
     * fall back to whatever it uses instead.
     */
    public static function of(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value) === '' ? null : $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $locale = self::locale();
        $preferred = $value[$locale] ?? null;

        if (is_string($preferred) && trim($preferred) !== '') {
            return $preferred;
        }

        foreach ($value as $translated) {
            if (is_string($translated) && trim($translated) !== '') {
                return $translated;
            }
        }

        return null;
    }

    private static function locale(): string
    {
        try {
            return (string) Container::getInstance()->make('translator')->getLocale();
        } catch (Throwable) {
            return '';
        }
    }
}
