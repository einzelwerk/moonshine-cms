<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * What the insertion modal offers: categories, a search over the titles, a card
 * per type and a card per variant when the schema declares variants. A separate
 * group lists the shared block library.
 *
 * Insertion into a slot of a container asks for the same list narrowed by the
 * nesting declarations: the white list of the slot and the allowed parents of
 * the type. The interface is a hint, the real check stays on the server.
 */
final readonly class BlockLibrary
{
    public function __construct(
        private BlockRegistryInterface $registry,
        private LibraryIcons $icons,
    ) {
    }

    /**
     * @return list<LibraryCategory>
     */
    public function categories(string $search = ''): array
    {
        return $this->build($this->entries($search), $search);
    }

    /**
     * The library for one named slot of one container.
     *
     * @return list<LibraryCategory>
     */
    public function categoriesForSlot(CmsBlock $parent, string $slot, string $search = ''): array
    {
        $definition = $this->registry->has($parent->type) ? $this->registry->make($parent->type) : null;

        if (! $definition instanceof ContainerBlockContract) {
            return [];
        }

        $declared = null;

        foreach ($definition->slots($parent->variant) as $candidate) {
            if ($candidate->name === $slot) {
                $declared = $candidate;

                break;
            }
        }

        if ($declared === null) {
            return [];
        }

        $entries = [];

        foreach ($this->entries($search) as $entry) {
            if (! $declared->accepts($entry->code)) {
                continue;
            }

            if (! $this->allowsParent($this->registry->schemaFor($entry->code), $parent->type)) {
                continue;
            }

            $entries[] = $entry;
        }

        return $this->build($entries, $search, shared: false);
    }

    /**
     * @return list<LibraryEntry>
     */
    private function entries(string $search): array
    {
        $entries = [];

        foreach (array_keys($this->registry->all()) as $code) {
            // An insertion of a library entry is not a type one picks: the
            // shared group offers the records themselves.
            if ($code === SharedInsertionBlock::code()) {
                continue;
            }

            $schema = $this->registry->schemaFor($code);
            $entry = new LibraryEntry(
                code: $code,
                title: $schema->title(),
                category: $schema->category(),
                iconKey: 'block-'.$code,
                icon: $this->icons->forType($code),
                about: $schema->description(),
                variants: $this->variantsOf($code, $schema),
            );

            if ($entry->matches($search)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<LibraryVariant>
     */
    private function variantsOf(string $code, BlockSchema $schema): array
    {
        $variants = [];

        foreach ($schema->variants() as $variantCode => $title) {
            $variants[] = new LibraryVariant(
                code: (string) $variantCode,
                title: $title,
                iconKey: 'block-'.$code.'-'.$variantCode,
                icon: $this->icons->forVariant($code, (string) $variantCode),
            );
        }

        return $variants;
    }

    /**
     * @return list<LibraryEntry>
     */
    private function sharedEntries(string $search): array
    {
        $entries = [];

        foreach (CmsSharedBlock::query()->orderBy('name')->get() as $sharedBlock) {
            $entry = new LibraryEntry(
                code: SharedInsertionBlock::code(),
                title: $sharedBlock->name,
                category: LibraryCategory::SHARED,
                iconKey: 'block-shared',
                icon: $this->icons->forType(SharedInsertionBlock::code()),
                shared: true,
                sharedBlockId: $sharedBlock->id,
                warning: (string) __('moonshine-cms::editor.shared_warning'),
            );

            if ($entry->matches($search)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  list<LibraryEntry>  $entries
     * @return list<LibraryCategory>
     */
    private function build(array $entries, string $search, bool $shared = true): array
    {
        /** @var array<string, list<LibraryEntry>> $grouped */
        $grouped = [];

        foreach ($entries as $entry) {
            $grouped[$entry->category ?? 'other'][] = $entry;
        }

        ksort($grouped);

        $categories = [];

        foreach ($grouped as $code => $categoryEntries) {
            $categories[] = new LibraryCategory(
                code: $code,
                title: (string) __('moonshine-cms::editor.categories.'.$code),
                entries: $categoryEntries,
            );
        }

        if (! $shared) {
            return $categories;
        }

        $sharedEntries = $this->sharedEntries($search);

        if ($sharedEntries !== []) {
            $categories[] = new LibraryCategory(
                code: LibraryCategory::SHARED,
                title: (string) __('moonshine-cms::editor.categories.shared'),
                entries: $sharedEntries,
                shared: true,
            );
        }

        return $categories;
    }

    private function allowsParent(BlockSchema $schema, string $parentType): bool
    {
        $parents = $schema->placementParents();

        if ($parents === '*') {
            return true;
        }

        return is_array($parents) && in_array($parentType, $parents, true);
    }
}
