<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Actions;

use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Types\PageTypeRegistry;

/**
 * The write of the page form, mounted on the resource as its save handler.
 *
 * A page is never stored by a bare `save()`: the address is derived from the
 * parent and the slug, and deriving it is exactly what {@see PageTree} does
 * together with every check the address has to survive — the cardinality of the
 * type, reserved prefixes, the depth of the tree and the collision of paths.
 */
final readonly class SavePage
{
    public function __construct(
        private PageTree $tree,
        private PageTypeRegistry $types,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  the submitted form, already applied to the model
     */
    public function __invoke(CmsPage $page, array $data): CmsPage
    {
        // The address of a singleton or a template is written in the
        // declaration; the parent chosen on the form means nothing to it.
        $fixed = $this->types->has($page->type) && $this->types->get($page->type)->hasFixedPath();

        if ($fixed) {
            $original = $page->getOriginal('parent_id');
            $page->parent_id = $page->exists && is_int($original) ? $original : null;
        }

        $parent = $page->parent_id === null ? null : PageModel::query()->find($page->parent_id);

        if (! $page->exists) {
            // The tree writes the address onto the very instance it is
            // given, so the resource keeps the model it built.
            $this->tree->create($page, $parent);

            return $page;
        }

        // A new parent moves the page with its whole subtree; the address
        // is rebuilt from the parent and the slug, so a slug changed in the
        // same submission travels along.
        if ($page->isDirty('parent_id')) {
            $this->tree->move($page, $parent);

            return $page;
        }

        // A renamed slug rewrites the address of the page and of its whole
        // subtree; the rest of the submitted values are saved with it.
        if ($page->isDirty('slug')) {
            $this->tree->rename($page, $page->slug);

            return $page;
        }

        $page->save();

        return $page;
    }
}
