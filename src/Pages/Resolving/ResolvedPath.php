<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Resolving;

use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Types\PageType;
use Illuminate\Database\Eloquent\Model;

/**
 * What an address resolved to.
 *
 * A miss is this object with `found` false, never a model row and never null:
 * the caller reads one flag instead of guessing from an absent value.
 *
 * The object carries the material of the answer — the page that owns the
 * blocks, the type declaration and the subject — and not the answer itself.
 * Rendering blocks, language versions and metadata belongs to the transport,
 * which is where the request and its block parameters live.
 */
final readonly class ResolvedPath
{
    /**
     * @param  string  $path  the normalized address that was resolved
     * @param  CmsPage|null  $page  the page owning the blocks: the page itself, or the template of the subject's type
     * @param  Model|null  $subject  the entity behind a template page type
     * @param  string|null  $language  the language code found in the address, for {@see Resolution::LANGUAGE_PREFIX}
     */
    private function __construct(
        public Resolution $outcome,
        public string $path,
        public bool $found,
        public ?CmsPage $page = null,
        public ?PageType $type = null,
        public ?Model $subject = null,
        public ?string $language = null,
    ) {
    }

    public static function page(string $path, CmsPage $page, PageType $type): self
    {
        return new self(Resolution::PAGE, $path, true, $page, $type);
    }

    /**
     * @param  CmsPage  $template  the single template page of the type, which owns the blocks
     */
    public static function subject(string $path, CmsPage $template, PageType $type, Model $subject): self
    {
        return new self(Resolution::SUBJECT, $path, true, $template, $type, $subject);
    }

    public static function languagePrefix(string $path, string $language): self
    {
        return new self(Resolution::LANGUAGE_PREFIX, $path, false, language: $language);
    }

    public static function notFound(string $path): self
    {
        return new self(Resolution::NOT_FOUND, $path, false);
    }

    public function layoutCode(): ?string
    {
        return $this->page?->layout_code;
    }
}
