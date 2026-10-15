<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Pages\Links\PageLinkResolver;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;

beforeEach(function (): void {
    TestPageTypes::registerAll(app(PageTypeRegistry::class));
});

function pageLink(mixed $uuid): Link
{
    return new Link(LinkKind::PAGE, ['page' => $uuid]);
}

function pageResolver(): PageLinkResolver
{
    return app(PageLinkResolver::class);
}

function storePage(string $slug, bool $active, string $type = 'page'): CmsPage
{
    return CmsPage::query()->create([
        'type' => $type,
        'name' => ['ru' => $slug],
        'slug' => $slug,
        'path' => '/'.$slug,
        'depth' => 0,
        'is_active' => $active,
    ]);
}

it('resolves an active page to its address', function (): void {
    $page = storePage('agb', active: true);

    $resolved = pageResolver()->resolve(pageLink($page->uuid), 'ru');

    expect($resolved->href)->toBe('/agb')
        ->and($resolved->kind)->toBe(LinkKind::PAGE)
        ->and($resolved->external)->toBeFalse();
});

it('hands out no address when the target is gone or was never named', function (): void {
    $resolver = pageResolver();

    expect($resolver->resolve(pageLink('3f1d2c4e-0000-4000-8000-000000000000'), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(pageLink(null), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(pageLink('  '), 'ru')->href)->toBeNull()
        ->and($resolver->resolve(new Link(LinkKind::PAGE), 'ru')->href)->toBeNull();
});

it('hands out no address for a page that has none of its own', function (string $slug, bool $active, string $type): void {
    $page = storePage($slug, active: $active, type: $type);

    expect(pageResolver()->resolve(pageLink($page->uuid), 'ru')->href)->toBeNull();
})->with([
    'switched off' => ['draft', false, 'page'],
    // A template holds the blocks of a whole type and is opened through its
    // subject, never by itself.
    'a template, which has no address of its own' => ['concert-template', true, 'concert'],
]);

it('keeps the new tab choice of the editor', function (): void {
    $page = storePage('kontakt', active: true);

    $link = new Link(LinkKind::PAGE, ['page' => $page->uuid], newTab: true);

    expect(pageResolver()->resolve($link, 'ru')->newTab)->toBeTrue();
});
