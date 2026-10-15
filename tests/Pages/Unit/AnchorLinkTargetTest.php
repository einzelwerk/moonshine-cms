<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Support\Facades\Validator;

/*
 * The half of the `anchor` kind that needs pages: the resolver lives in the
 * blocks package and asks whoever owns pages which record a target names.
 */

beforeEach(function (): void {
    TestPageTypes::registerAll(app(PageTypeRegistry::class));
});

function anchoredPage(string $slug = 'contacts', bool $active = true): CmsPage
{
    $page = app(PageTree::class)->create(new CmsPage([
        'type' => 'page',
        'slug' => $slug,
        'name' => ['ru' => 'Контакты'],
        'is_active' => $active,
    ]));

    $page->blocks()->create([
        'type' => TextBlock::code(),
        'name' => 'Текст',
        'anchor' => 'kontakty',
        'content' => [],
        'is_active' => false,
    ]);

    return $page;
}

function anchorFails(array $attributes): bool
{
    return Validator::make(
        ['link' => ['kind' => 'anchor', 'attributes' => $attributes]],
        ['link' => LinkField::make('cta')->rules()],
    )->fails();
}

it('puts the address of the page in front of the anchor', function (): void {
    $page = anchoredPage();

    $href = app(LinkResolverRegistry::class)
        ->resolve(new Link(LinkKind::ANCHOR, ['anchor' => 'kontakty', 'page' => $page->uuid]), 'ru')
        ->href;

    expect($href)->toBe('/contacts#kontakty');
});

it('accepts an anchor the page carries, the draft counted, and refuses one it does not', function (): void {
    $page = anchoredPage();

    // A block added and not published yet is still the block being pointed at,
    // so the anchor is looked for among the live rows and not in a snapshot.
    expect(anchorFails(['anchor' => 'kontakty', 'page' => $page->uuid]))->toBeFalse()
        ->and(anchorFails(['anchor' => 'raspisanie', 'page' => $page->uuid]))->toBeTrue();
});

it('leaves a link to a page that is gone savable, a deleted target being content', function (): void {
    expect(anchorFails(['anchor' => 'kontakty', 'page' => '9f3c1a2b-4e55-4c1e-9a3f-0b71d2c35e10']))->toBeFalse();
});
