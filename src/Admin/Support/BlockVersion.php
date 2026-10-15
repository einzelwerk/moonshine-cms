<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Support;

use Ewk\Cms\Blocks\Models\CmsBlock;

/**
 * The version marker of a block card. The timestamp of the row is the version:
 * it already changes on every write, so optimistic locking needs no extra
 * column and no migration.
 *
 * The marker is formatted down to microseconds on purpose. A `timestamp`
 * column carries whole seconds, a `timestamp(6)` column carries more, and the
 * same code covers both.
 */
final readonly class BlockVersion
{
    public const FORMAT = 'Y-m-d H:i:s.u';

    public static function of(CmsBlock $block): string
    {
        return $block->updated_at?->format(self::FORMAT) ?? '';
    }

    public static function matches(CmsBlock $block, ?string $expected): bool
    {
        return $expected !== null && $expected !== '' && $expected === self::of($block);
    }

    private function __construct()
    {
    }
}
