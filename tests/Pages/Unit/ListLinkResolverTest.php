<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Tests\Fixtures\Blocks\ListingBlock;
use Ewk\Cms\Pages\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Support\Facades\Validator;

/*
 * The `list` kind of a link: the target page and the values of the filters
 * are stored, and the anchor those values live under is found here.
 */

beforeEach(function (): void {
    TestPageTypes::registerAll(app(PageTypeRegistry::class));
});

function listing(CmsPage $page, string $anchor, bool $active = true): void
{
    $page->blocks()->create([
        'type' => ListingBlock::code(),
        'name' => 'Список '.$anchor,
        'anchor' => $anchor,
        'content' => [],
        'is_active' => $active,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function resolveList(array $attributes): ?string
{
    return app(LinkResolverRegistry::class)
        ->resolve(new Link(LinkKind::LISTING, $attributes), 'ru')
        ->href;
}

it('builds the address of the catalogue out of the single listing block that declares the filters', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');

    expect(resolveList(['page' => $page->uuid, 'filters' => ['city' => 'praha']]))
        ->toBe('/events?b[afisha][city]=praha');
});

it('writes the query string the way the whole CMS writes it', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');

    // Keys sorted, brackets of the namespace readable, values escaped: the same
    // encoder the pagination links of the very same block use.
    expect(resolveList(['page' => $page->uuid, 'filters' => ['year' => 2027, 'city' => 'Praha 1']]))
        ->toBe('/events?b[afisha][city]=Praha%201&b[afisha][year]=2027');
});

it('takes the anchor the editor named when the page carries several lists', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');
    listing($page, 'arhiv');

    expect(resolveList(['page' => $page->uuid, 'anchor' => 'arhiv', 'filters' => ['city' => 'praha']]))
        ->toBe('/events?b[arhiv][city]=praha');
});

it('hands out a broken link when several lists match and no anchor was named', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');
    listing($page, 'arhiv');

    // Guessing one of several lists would silently filter the wrong one.
    expect(resolveList(['page' => $page->uuid, 'filters' => ['city' => 'praha']]))->toBeNull();
});

it('hands out a broken link when no block declares every filter', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');

    expect(resolveList(['page' => $page->uuid, 'filters' => ['city' => 'praha', 'genre' => 'jazz']]))->toBeNull();
});

it('hands out a broken link for a named anchor the page does not carry', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');

    expect(resolveList(['page' => $page->uuid, 'anchor' => 'gone', 'filters' => ['city' => 'praha']]))->toBeNull();
});

it('hands out a broken link for a missing, deleted or switched off page', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');

    expect(resolveList(['filters' => ['city' => 'praha']]))->toBeNull()
        ->and(resolveList(['page' => '11111111-2222-3333-4444-555555555555']))->toBeNull();

    $page->update(['is_active' => false]);

    expect(resolveList(['page' => $page->uuid, 'filters' => ['city' => 'praha']]))->toBeNull();
});

it('ignores a switched off list when looking for the anchor', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');
    listing($page, 'arhiv', active: false);

    expect(resolveList(['page' => $page->uuid, 'filters' => ['city' => 'praha']]))
        ->toBe('/events?b[afisha][city]=praha');
});

it('answers the plain address of the page when there is no filter to place, whatever the lists on it', function (): void {
    $page = Make::page('events');
    listing($page, 'afisha');
    listing($page, 'arhiv');

    // Two lists are not ambiguous until a value has to land in one of them, so
    // a link without filters is simply the address of the page.
    expect(resolveList(['page' => $page->uuid]))->toBe('/events')
        ->and(resolveList(['page' => $page->uuid, 'filters' => []]))->toBe('/events')
        ->and(resolveList(['page' => $page->uuid, 'filters' => ['city' => '']]))->toBe('/events');
});

it('answers the plain address of a page that carries no list at all', function (): void {
    $page = Make::page('info');

    expect(resolveList(['page' => $page->uuid]))->toBe('/info');
});

it('hands out a broken link for a template page, which has no address of its own', function (): void {
    $template = new CmsPage([
        'type' => 'concert',
        'slug' => 'concert-template',
        'name' => ['ru' => 'Шаблон'],
        'is_active' => true,
    ]);
    app(PageTree::class)->create($template);
    listing($template, 'afisha');

    expect(resolveList(['page' => $template->uuid, 'filters' => ['city' => 'praha']]))->toBeNull()
        ->and(resolveList(['page' => $template->uuid]))->toBeNull();
});

it('refuses a catalogue link with no page in the form, filters or not', function (): void {
    $page = Make::page('events');
    $rules = ['link' => LinkField::make('cta')->rules()];

    $fails = static fn (array $attributes): bool => Validator::make(
        ['link' => ['kind' => 'list', 'attributes' => $attributes]],
        $rules,
    )->fails();

    // Without the page there is nothing to build an address out of: the link
    // would be stored already dead.
    expect($fails(['page' => $page->uuid]))->toBeFalse()
        ->and($fails(['page' => $page->uuid, 'filters' => ['city' => 'praha']]))->toBeFalse()
        ->and($fails(['filters' => ['city' => 'praha']]))->toBeTrue()
        ->and($fails([]))->toBeTrue();
});
