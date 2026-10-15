<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Localization;

/**
 * The one deterministic reading order for a translatable value: requested
 * language, default language, empty value. There is no "first non-empty
 * language" step — it would mix languages on one page.
 */
final class LocalizedValueResolver
{
    /**
     * A missing key, `null` and an empty string all mean "no translation".
     *
     * @param  array<array-key, mixed>|string|null  $stored
     */
    public static function resolve(array|string|null $stored, string $requested, string $default): string
    {
        if (is_string($stored)) {
            return $stored;
        }

        if ($stored === null) {
            return '';
        }

        return self::valueOf($stored, $requested)
            ?? self::valueOf($stored, $default)
            ?? '';
    }

    /**
     * @param  array<array-key, mixed>  $stored
     */
    private static function valueOf(array $stored, string $locale): ?string
    {
        $value = $stored[$locale] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
