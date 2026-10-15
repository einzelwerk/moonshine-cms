<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Rules;

use Closure;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Types\Cardinality;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The cardinality of the type, checked where the page is created: a singleton
 * holds exactly one page, a template type one row — its address is the path
 * template of the declaration and the path column is unique. The tree refuses
 * both too; the form is where the editor learns about it instead of meeting an
 * error toast.
 */
final readonly class PageTypeCardinalityRule implements ValidationRule
{
    public function __construct(
        private PageTypeRegistry $registry,
        private CmsPage $page,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = is_string($value) && $this->registry->has($value) ? $this->registry->get($value) : null;

        if ($type === null || $type->cardinality() === Cardinality::MANY) {
            return;
        }

        $siblings = PageModel::query()->where('type', $type->key());

        if ($this->page->exists) {
            $siblings->whereKeyNot($this->page->id);
        }

        if (! $type->isTemplate()) {
            if ($siblings->exists()) {
                $fail((string) __('moonshine-cms::pages.singleton_exists', ['type' => $type->title()]));
            }

            return;
        }

        $conflict = $siblings->first();

        if ($conflict !== null) {
            $caption = Translated::caption($conflict->getAttribute('name'));

            $fail((string) __('moonshine-cms::pages.template_exists', [
                'type' => $type->title(),
                'page' => $caption !== '' ? $caption : '#'.$conflict->id,
            ]));
        }
    }
}
