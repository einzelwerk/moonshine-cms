<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Selection;

use Ewk\Cms\Layouts\Models\CmsLayout;

/**
 * The answer of {@see LayoutSelector}, shaped the way the pages package
 * shapes a resolved address: a miss is this object with `found` false, never
 * a bare null.
 *
 * The reason is the {@see LayoutSelection::NO_DEFAULT} case. An installation
 * without a default layout is a configuration mistake, and a plain null would
 * arrive at the caller indistinguishable from "the page has no layout" — the
 * one thing this service exists to answer. With an outcome on the result the
 * caller either delivers a code or knowingly falls back to the shell the
 * frontend keeps for unknown codes.
 */
final readonly class SelectedLayout
{
    /**
     * @param  string|null  $requestedCode  what the page asked for, kept for the diagnostics of a fallback
     */
    private function __construct(
        public LayoutSelection $outcome,
        public bool $found,
        public ?CmsLayout $layout = null,
        public ?string $requestedCode = null,
    ) {
    }

    public static function requested(CmsLayout $layout): self
    {
        return new self(LayoutSelection::REQUESTED, true, $layout, $layout->code);
    }

    public static function fallback(CmsLayout $layout, ?string $requestedCode): self
    {
        return new self(LayoutSelection::DEFAULT, true, $layout, $requestedCode);
    }

    public static function noDefault(?string $requestedCode): self
    {
        return new self(LayoutSelection::NO_DEFAULT, false, null, $requestedCode);
    }

    /**
     * The code to hand to the frontend, or null when the installation has
     * nothing to hand over.
     */
    public function code(): ?string
    {
        return $this->layout?->code;
    }

    /**
     * True when the page asked for a layout it did not get: an unknown or a
     * deactivated code. Worth a log line in the application, and the reason
     * the requested code survives on this object.
     */
    public function fellBack(): bool
    {
        return $this->outcome !== LayoutSelection::REQUESTED
            && $this->requestedCode !== null
            && $this->requestedCode !== '';
    }
}
