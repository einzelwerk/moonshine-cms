<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\MoonShineCms\Editor\BlockLibrary;
use Ewk\MoonShineCms\Editor\LibraryCategory;
use Ewk\MoonShineCms\Editor\LibraryEntry;
use Ewk\MoonShineCms\Editor\LibraryVariant;

function library(): BlockLibrary
{
    return app(BlockLibrary::class);
}

/**
 * @param  list<LibraryCategory>  $categories
 * @return list<string>
 */
function libraryCodes(array $categories): array
{
    $codes = [];

    foreach ($categories as $category) {
        foreach ($category->entries as $entry) {
            $codes[] = $entry->code;
        }
    }

    return $codes;
}

/**
 * @param  list<LibraryCategory>  $categories
 * @return array<string, LibraryEntry>
 */
function libraryEntries(array $categories): array
{
    $entries = [];

    foreach ($categories as $category) {
        foreach ($category->entries as $entry) {
            $entries[$entry->code] = $entry;
        }
    }

    return $entries;
}

it('groups the registered types by the category of their schema', function (): void {
    $grouped = [];

    foreach (library()->categories() as $category) {
        $grouped[$category->code] = array_map(
            static fn (LibraryEntry $entry): string => $entry->code,
            $category->entries,
        );
    }

    expect($grouped)->toHaveKeys(['content', 'layout'])
        ->and($grouped['layout'])->toContain('panel', 'columns')
        ->and($grouped['content'])->toContain('text', 'every_field');
});

it('never offers the insertion type itself: the shared group offers the records', function (): void {
    expect(libraryCodes(library()->categories()))->not->toContain('shared');
});

it('narrows the list by the title search', function (): void {
    expect(libraryCodes(library()->categories('Панель')))->toBe(['panel'])
        ->and(libraryCodes(library()->categories('ничего такого')))->toBe([]);
});

it('offers the declared variants as their own cards', function (): void {
    $entries = libraryEntries(library()->categories());

    $variants = array_map(
        static fn (LibraryVariant $variant): string => $variant->code,
        $entries['every_field']->variants,
    );

    expect($variants)->toBe(['wide', 'narrow'])
        ->and($entries['panel']->variants)->toBe([])
        ->and($entries['every_field']->iconKey)->toBe('block-every_field');
});

it('draws the icon the configuration names for a type, for a variant and for neither', function (): void {
    config()->set('moonshine-cms.block_icons', [
        'every_field' => 'photo',
        'every_field.narrow' => 'film',
        // A name the icon set does not hold: the card keeps a picture.
        'panel' => 'no-such-icon',
    ]);

    $entries = libraryEntries(library()->categories());
    $variants = $entries['every_field']->variants;

    expect($entries['every_field']->icon)->toContain('<svg')
        // The variant without an entry of its own takes the icon of its type.
        ->and($variants[0]->code)->toBe('wide')
        ->and($variants[0]->icon)->toBe($entries['every_field']->icon)
        ->and($variants[1]->code)->toBe('narrow')
        ->and($variants[1]->icon)->toContain('<svg')
        ->and($variants[1]->icon)->not->toBe($entries['every_field']->icon)
        // A code nobody declared, and a declared name that does not exist,
        // both fall back to the same neutral glyph.
        ->and($entries['panel']->icon)->toContain('<svg')
        ->and($entries['panel']->icon)->toBe($entries['text']->icon)
        ->and($entries['panel']->icon)->not->toBe($entries['every_field']->icon);
});

it('marks a shared library record and warns that it shows everywhere', function (): void {
    CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Промо', 'is_active' => true]);

    $shared = array_values(array_filter(
        library()->categories(),
        static fn (LibraryCategory $category): bool => $category->shared,
    ));

    expect($shared)->toHaveCount(1)
        ->and($shared[0]->entries[0]->title)->toBe('Промо')
        ->and($shared[0]->entries[0]->shared)->toBeTrue()
        ->and($shared[0]->entries[0]->sharedBlockId)->not->toBeNull()
        ->and($shared[0]->entries[0]->warning)->not->toBeNull();
});

it('offers a slot only the types it accepts and no shared group', function (): void {
    $owner = editorOwner();
    $panel = cmsBlock($owner, ['type' => 'panel', 'name' => 'Панель']);

    CmsSharedBlock::query()->create(['code' => 'promo', 'name' => 'Промо', 'is_active' => true]);

    expect(libraryCodes(library()->categoriesForSlot($panel, 'body')))->toBe(['text'])
        ->and(library()->categoriesForSlot($panel, 'nope'))->toBe([]);
});

it('returns nothing for a slot of a block that is not a container', function (): void {
    $text = cmsBlock(editorOwner());

    expect(library()->categoriesForSlot($text, 'body'))->toBe([]);
});
