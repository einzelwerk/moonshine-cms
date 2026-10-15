<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Illuminate\Support\Carbon;

/**
 * Readonly view of everything the status area next to the publish button
 * shows: the computed state, when the snapshot was written, which languages
 * are not filled completely and what would stand in the way of a publication.
 *
 * None of it blocks a save: a draft must be savable in any state.
 */
final readonly class PublicationState
{
    /**
     * @param  list<string>  $incompleteLocales
     * @param  list<string>  $problems
     */
    public function __construct(
        public PublicationStatus $status,
        public ?Carbon $publishedAt = null,
        public int $number = 0,
        public array $incompleteLocales = [],
        public array $problems = [],
    ) {
    }

    public function label(): string
    {
        return $this->status->label();
    }

    public function tone(): string
    {
        return $this->status->tone();
    }

    public function isPublishable(): bool
    {
        return $this->problems === [];
    }
}
