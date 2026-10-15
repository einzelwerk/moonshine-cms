<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Layouts;

/**
 * One shell an installation declares.
 *
 * The code is the contract with the frontend: every code has a component
 * there, so the dictionary is closed and lives in code next to the release
 * that ships those components. The row of `cms_layouts` keeps the settings an
 * editor changes — which header and footer, the width, the default flag; this
 * declaration keeps what an editor may not invent.
 */
final readonly class LayoutDefinition
{
    public function __construct(
        public string $code,
        public string $title,
        public string $description = '',
    ) {
    }

    public static function make(string $code, string $title, string $description = ''): self
    {
        return new self($code, $title, $description);
    }
}
