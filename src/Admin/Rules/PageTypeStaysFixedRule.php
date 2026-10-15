<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Rules;

use Closure;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The address of a singleton and of a template is written in the declaration,
 * and the address of a page of the tree is built from its slug: those are
 * different mechanisms, so an existing page does not swap one for the other.
 */
final readonly class PageTypeStaysFixedRule implements ValidationRule
{
    public function __construct(
        private PageTypeRegistry $registry,
        private CmsPage $page,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->page->exists || ! is_string($value) || $value === $this->page->type) {
            return;
        }

        if ($this->fixed($value) || $this->fixed($this->page->type)) {
            $fail((string) __('moonshine-cms::pages.type_is_fixed'));
        }
    }

    private function fixed(string $key): bool
    {
        return $this->registry->has($key) && $this->registry->get($key)->hasFixedPath();
    }
}
