<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Exceptions;

use Ewk\Cms\Blocks\Models\CmsBlock;
use RuntimeException;

/**
 * The row changed between opening the card and saving it: protection against a
 * race is a comparison of the version at save time, not a permanent
 * synchronisation. The save writes nothing and the editor is told what
 * happened.
 */
final class BlockVersionConflictException extends RuntimeException
{
    private function __construct(
        public readonly string $expected,
        public readonly string $actual,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forBlock(CmsBlock $block, ?string $expected, string $actual): self
    {
        return new self(
            expected: $expected ?? '',
            actual: $actual,
            message: (string) __('moonshine-cms::editor.conflict', ['name' => $block->name]),
        );
    }
}
