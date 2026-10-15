<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Enums;

/**
 * The values `og:type` may carry. The set is small on purpose: Open Graph
 * declares dozens of types with vertical specific properties, and a value we do
 * not also fill the properties of is worse than the default `website`.
 *
 * An empty column means the default, which the document builder writes; the
 * enumeration exists for the form and for validation, never for storage.
 */
enum OpenGraphType: string
{
    case WEBSITE = 'website';

    case ARTICLE = 'article';

    case BOOK = 'book';

    case PROFILE = 'profile';

    case VIDEO = 'video.movie';

    case MUSIC = 'music.song';

    case PRODUCT = 'product';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->value;
        }

        return $options;
    }
}
