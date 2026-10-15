<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Support;

use Ewk\Cms\Pages\Exceptions\InvalidSlugException;
use Illuminate\Support\Str;

/**
 * One segment, strict kebab, the very same rule tours and events already
 * follow. The old page rule that allowed spaces, capitals and slashes is
 * cancelled: a slash inside a slug is a hidden second mechanism of nesting.
 */
final class Slug
{
    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function isValid(string $slug): bool
    {
        return $slug !== ''
            && mb_strlen($slug) <= PageLimits::SLUG_LENGTH
            && preg_match(self::PATTERN, $slug) === 1;
    }

    /**
     * @throws InvalidSlugException
     */
    public static function assert(string $slug): void
    {
        if (mb_strlen($slug) > PageLimits::SLUG_LENGTH) {
            throw InvalidSlugException::tooLong($slug);
        }

        if (! self::isValid($slug)) {
            throw InvalidSlugException::notKebab($slug);
        }
    }

    /**
     * Generation keeps the existing behaviour: transliteration driven by the
     * language of the source text, then the same kebab rule.
     *
     * @param  string|null  $language  language of the source text, for transliteration
     *
     * @throws InvalidSlugException when the source carries nothing a slug can be built from
     */
    public static function generate(string $source, ?string $language = null): string
    {
        $slug = Str::slug($source, '-', $language ?? 'en');

        if (mb_strlen($slug) > PageLimits::SLUG_LENGTH) {
            $slug = rtrim(mb_substr($slug, 0, PageLimits::SLUG_LENGTH), '-');
        }

        if (! self::isValid($slug)) {
            throw InvalidSlugException::fromSource($source);
        }

        return $slug;
    }
}
