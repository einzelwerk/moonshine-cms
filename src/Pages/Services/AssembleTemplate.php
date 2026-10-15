<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Services;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Pages\Exceptions\UnknownPageTypeException;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Assembling the template of a type from its starter set: the declaration says
 * what a freshly built template starts with, and this is what the «assemble»
 * button of the admin panel does.
 *
 * The row is created inactive. A template renders for every entity of its
 * type the moment it becomes active, so a half-filled one must not go live
 * just because somebody pressed the button.
 */
final readonly class AssembleTemplate
{
    public function __construct(
        private PageTypeRegistry $types,
        private BlockRegistryInterface $blocks,
        private LanguageCatalog $languages,
        private PageTree $tree,
    ) {
    }

    /**
     * The existing template of a type, or null when nobody assembled one. An
     * inactive row is the template as a draft, so it counts: the tile shows
     * it instead of offering to build a second one.
     */
    public function existing(string $typeKey): ?CmsPage
    {
        return PageModel::query()
            ->where('type', $typeKey)
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * @throws UnknownPageTypeException
     * @throws InvalidArgumentException when the type is not a template type
     */
    public function __invoke(string $typeKey): CmsPage
    {
        $type = $this->types->get($typeKey);

        if (! $type->isTemplate()) {
            throw new InvalidArgumentException(sprintf('Page type [%s] is not a template type.', $typeKey));
        }

        return DB::transaction(function () use ($type): CmsPage {
            $page = PageModel::make([
                'type' => $type->key(),
                'name' => [$this->languages->defaultLocale() => $type->title()],
                'slug' => $type->key().'-template',
                'is_active' => false,
            ]);

            $this->tree->create($page);

            foreach ($type->starter() as $position => $code) {
                // A starter set may name a code an installation has switched
                // off; that is not a reason to refuse the whole template.
                if (! $this->blocks->has($code)) {
                    continue;
                }

                $page->blocks()->create([
                    'type' => $code,
                    'name' => $this->blocks->schemaFor($code)->title(),
                    'content' => [],
                    'sort_order' => $position,
                ]);
            }

            return $page;
        });
    }
}
