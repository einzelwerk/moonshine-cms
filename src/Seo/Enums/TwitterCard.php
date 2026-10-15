<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Enums;

/**
 * The kinds of Twitter card. An empty column means the card is chosen when the
 * document is handed out: a large one when there is a picture, a small one
 * otherwise.
 */
enum TwitterCard: string
{
    case SUMMARY = 'summary';

    case SUMMARY_LARGE_IMAGE = 'summary_large_image';

    case APP = 'app';

    case PLAYER = 'player';

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
