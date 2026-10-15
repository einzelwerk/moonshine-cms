<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Contracts\DeclaresRequiredBlocks;
use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Fields\RichTextField;
use Ewk\Cms\Blocks\Fields\TextareaField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\MoonShineCms\Support\BlockVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The tree of cards the editor draws, built from the rows of one owner in
 * editor order. Nested blocks are nested cards and every slot is labelled.
 *
 * Locks do not exist in the first version: the structure of a template is held
 * by required blocks, so the last instance of a required type gets no delete
 * button and no activity switch.
 */
final readonly class EditorTree
{
    /**
     * How long the head of a card quotes the content before it trails off.
     */
    private const CAPTION_LENGTH = 70;

    public function __construct(
        private BlockRegistryInterface $registry,
        private LibraryIcons $icons,
        private LanguageCatalog $catalog,
    ) {
    }

    /**
     * @return list<BlockCard>
     */
    public function rootCards(Model $owner): array
    {
        /** @var Collection<int, CmsBlock> $rows */
        $rows = CmsBlock::query()
            ->where('blockable_type', $owner->getMorphClass())
            ->where('blockable_id', $owner->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $required = $owner instanceof DeclaresRequiredBlocks ? $owner->requiredBlockCodes() : [];
        $counts = [];

        foreach ($rows as $row) {
            $counts[$row->type] = ($counts[$row->type] ?? 0) + 1;
        }

        $names = $this->sharedNames($rows);

        $cards = [];

        foreach ($rows as $row) {
            if ($row->parent_id === null) {
                $cards[] = $this->card($row, $rows, $required, $counts, $names);
            }
        }

        return $cards;
    }

    /**
     * @param  Collection<int, CmsBlock>  $rows
     * @param  list<string>  $required
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $sharedNames
     */
    private function card(CmsBlock $row, Collection $rows, array $required, array $counts, array $sharedNames): BlockCard
    {
        $known = $this->registry->has($row->type);
        $schema = $known ? $this->registry->schemaFor($row->type) : null;
        $shared = $row->cms_shared_block_id !== null;
        $protected = in_array($row->type, $required, true) && ($counts[$row->type] ?? 0) <= 1;

        return new BlockCard(
            id: $row->id,
            uuid: $row->uuid,
            type: $row->type,
            typeTitle: $shared
                ? ($sharedNames[$row->cms_shared_block_id] ?? $row->name)
                : ($schema?->title() ?? $row->type),
            name: $row->name,
            caption: $shared ? '' : $this->caption($row, $schema),
            variant: $row->variant,
            variantTitle: $this->variantTitle($row, $schema?->variants() ?? []),
            anchor: $row->anchor,
            isActive: $row->is_active,
            version: BlockVersion::of($row),
            shared: $shared,
            sharedWarning: $shared ? (string) __('moonshine-cms::editor.shared_warning') : null,
            sharedBlockId: $row->cms_shared_block_id,
            removable: ! $protected,
            switchable: ! $protected,
            known: $known,
            icon: $this->icons->forType($row->type),
            content: $row->content ?? [],
            slots: $this->slots($row, $rows, $required, $counts, $sharedNames),
        );
    }

    /**
     * @param  Collection<int, CmsBlock>  $rows
     * @param  list<string>  $required
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $sharedNames
     * @return list<SlotView>
     */
    private function slots(CmsBlock $row, Collection $rows, array $required, array $counts, array $sharedNames): array
    {
        $definition = $this->registry->has($row->type) ? $this->registry->make($row->type) : null;

        if (! $definition instanceof ContainerBlockContract) {
            return [];
        }

        $views = [];

        foreach ($definition->slots($row->variant) as $slot) {
            $children = [];

            foreach ($rows as $candidate) {
                if ($candidate->parent_id === $row->getKey() && $candidate->slot === $slot->name) {
                    $children[] = $this->card($candidate, $rows, $required, $counts, $sharedNames);
                }
            }

            $views[] = new SlotView(
                name: $slot->name,
                title: $slot->title,
                span: $slot->span,
                min: $slot->min,
                max: $slot->max,
                children: $children,
            );
        }

        return $views;
    }

    /**
     * What the block says about itself: the first text of the declaration that
     * carries a value in the language of the panel's default. Markup and
     * repeated whitespace are stripped — the head of a card is one line, not a
     * preview.
     */
    private function caption(CmsBlock $row, ?BlockSchema $schema): string
    {
        if (! $schema instanceof BlockSchema) {
            return '';
        }

        $content = $row->content ?? [];

        foreach ($schema->fields() as $field) {
            if (! $this->isTextual($field)) {
                continue;
            }

            $value = $content[$field->name()] ?? null;

            if ($field->isTranslatable() && is_array($value)) {
                $value = $value[$this->catalog->defaultLocale()] ?? null;
            }

            if (! is_string($value)) {
                continue;
            }

            $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

            // A heading that repeats the caption of the type says nothing: the
            // head would read «Расписание Расписание».
            if ($text !== '' && mb_strtolower($text) === mb_strtolower($schema->title())) {
                return '';
            }

            if ($text !== '') {
                return Str::limit($text, self::CAPTION_LENGTH);
            }
        }

        return '';
    }

    private function isTextual(Field $field): bool
    {
        return $field instanceof TextField
            || $field instanceof TextareaField
            || $field instanceof RichTextField;
    }

    /**
     * @param  array<array-key, string>  $variants
     */
    private function variantTitle(CmsBlock $row, array $variants): ?string
    {
        if ($row->variant === null) {
            return null;
        }

        return $variants[$row->variant] ?? null;
    }

    /**
     * Names of the library entries the rows point at, in one query.
     *
     * @param  Collection<int, CmsBlock>  $rows
     * @return array<int, string>
     */
    private function sharedNames(Collection $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            if ($row->cms_shared_block_id !== null) {
                $ids[$row->cms_shared_block_id] = $row->cms_shared_block_id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach (CmsSharedBlock::query()->whereIn('id', $ids)->get(['id', 'name']) as $shared) {
            $names[$shared->id] = $shared->name;
        }

        return $names;
    }
}
