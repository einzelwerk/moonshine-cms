<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\MoonShineCms\Links\LinkChoice;
use Ewk\MoonShineCms\Links\LinkChoices;
use Ewk\MoonShineCms\Tests\Fixtures\Catalogue;
use Ewk\MoonShineCms\Tests\Fixtures\TestPageTypes;

/**
 * @return list<string>
 */
function choiceIds(array $choices): array
{
    return array_map(static fn (LinkChoice $choice): string => $choice->id, $choices);
}

it('offers the kinds the field allows and nothing else', function (): void {
    $choices = LinkChoices::build(['url', 'email'], app(PageTypeRegistry::class));

    expect(choiceIds($choices))->toBe(['url', 'email']);
});

it('spells the entity kind out as one entry per class an installation gives an address to', function (): void {
    $choices = LinkChoices::build(['entity'], app(PageTypeRegistry::class));

    // Two template types stand over the same class, and one class is one
    // answer to «where does it lead».
    expect($choices)->toHaveCount(1)
        ->and($choices[0]->kind)->toBe('entity')
        ->and($choices[0]->entityType)->toBe(Catalogue::class)
        // The caption comes from the declaration of the type and is never the
        // name of the class.
        ->and($choices[0]->label)->toBe('Шаблон концерта')
        ->and($choices[0]->id)->toBe('entity:'.Catalogue::class);
});

it('offers no entity entry where no page type stands over a class', function (): void {
    $empty = new PageTypeRegistry();
    $empty->register(TestPageTypes::page());

    expect(LinkChoices::build(['entity', 'url'], $empty))->toHaveCount(1)
        ->and(choiceIds(LinkChoices::build(['entity', 'url'], $empty)))->toBe(['url']);
});

it('keeps the order an editor reads, with the plain targets last', function (): void {
    $choices = LinkChoices::build([], app(PageTypeRegistry::class));

    expect(choiceIds($choices))->toBe([
        'page',
        'entity:'.Catalogue::class,
        'list',
        'anchor',
        'file',
        'action',
        'route',
        'url',
        'email',
        'phone',
    ]);
});

it('matches a stored link to the entry that saves it back', function (): void {
    $choices = LinkChoices::build([], app(PageTypeRegistry::class));

    expect(LinkChoices::match($choices, 'entity', Catalogue::class)?->id)->toBe('entity:'.Catalogue::class)
        ->and(LinkChoices::match($choices, 'page', null)?->id)->toBe('page')
        // A class no type stands over any more has no entry, and the picker
        // falls back to its first one instead of showing a code.
        ->and(LinkChoices::match($choices, 'entity', 'App\\Models\\Gone'))->toBeNull()
        ->and(LinkChoices::match($choices, 'nonsense', null))->toBeNull();
});
