<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

/**
 * The publication indicator next to the publish button. It is computed from the
 * snapshot, the activity of the owner and the modification mark, and is never
 * stored as a state of the row.
 */
enum PublicationStatus: string
{
    /** Never published: no snapshot exists. */
    case DRAFT = 'draft';

    /** The snapshot is current. */
    case PUBLISHED = 'published';

    /** A snapshot exists and the draft has changed since. */
    case PUBLISHED_AND_MODIFIED = 'published_modified';

    /** A snapshot exists but the owner is switched off. */
    case UNPUBLISHED = 'unpublished';

    public function label(): string
    {
        return (string) __('moonshine-cms::editor.status.'.$this->value);
    }

    /**
     * Colour key for the badge. Neutral names, so the UI keeps its own
     * palette.
     */
    public function tone(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PUBLISHED => 'green',
            self::PUBLISHED_AND_MODIFIED => 'warning',
            self::UNPUBLISHED => 'error',
        };
    }
}
