<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Blocks\TextBlock;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Pages\Exceptions\InvalidPagePlacementException;
use Ewk\Cms\Pages\Exceptions\InvalidSlugException;
use Ewk\Cms\Pages\Exceptions\PageTypeCardinalityException;
use Ewk\Cms\Pages\Exceptions\PathCollisionException;
use Ewk\Cms\Pages\Exceptions\ReservedSegmentException;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;

beforeEach(function (): void {
    TestPageTypes::registerAll(app(PageTypeRegistry::class));
});

function tree(): PageTree
{
    return app(PageTree::class);
}

it('derives the address of a page from its parent and its slug', function (): void {
    $info = Make::page('info');
    $help = Make::page('help', $info);
    $faq = Make::page('faq', $help);

    expect($info->path)->toBe('/info')
        ->and($info->depth)->toBe(0)
        ->and($help->path)->toBe('/info/help')
        ->and($help->depth)->toBe(1)
        ->and($help->parent_id)->toBe($info->id)
        ->and($faq->path)->toBe('/info/help/faq')
        ->and($faq->depth)->toBe(2)
        ->and($info->uuid)->not->toBeEmpty();
});

it('rewrites the addresses of the whole subtree when a node moves', function (): void {
    $info = Make::page('info');
    $help = Make::page('help', $info);
    $faq = Make::page('faq', $help);
    $about = Make::page('about');

    tree()->move($help, $about);

    expect($help->fresh()?->path)->toBe('/about/help')
        ->and($help->fresh()?->depth)->toBe(1)
        ->and($faq->fresh()?->path)->toBe('/about/help/faq')
        ->and($faq->fresh()?->depth)->toBe(2)
        ->and($info->fresh()?->path)->toBe('/info');
});

it('rewrites the subtree when only the slug changes', function (): void {
    $info = Make::page('info');
    $help = Make::page('help', $info);

    tree()->rename($info, 'information');

    expect($info->fresh()?->path)->toBe('/information')
        ->and($help->fresh()?->path)->toBe('/information/help');
});

it('moves a node to the root', function (): void {
    $info = Make::page('info');
    $help = Make::page('help', $info);

    tree()->move($help, null);

    expect($help->fresh()?->path)->toBe('/help')
        ->and($help->fresh()?->depth)->toBe(0)
        ->and($help->fresh()?->parent_id)->toBeNull();
});

it('refuses to move a page into itself or into its own descendant', function (): void {
    $info = Make::page('info');
    $help = Make::page('help', $info);

    expect(fn () => tree()->move($info, $info))->toThrow(InvalidPagePlacementException::class, 'own parent')
        ->and(fn () => tree()->move($info, $help))->toThrow(InvalidPagePlacementException::class, 'own descendant')
        ->and($info->fresh()?->path)->toBe('/info');
});

it('holds five levels and refuses the sixth', function (): void {
    $parent = null;

    foreach (['l1', 'l2', 'l3', 'l4', 'l5'] as $slug) {
        $parent = Make::page($slug, $parent);
    }

    expect($parent?->path)->toBe('/l1/l2/l3/l4/l5')
        ->and($parent?->depth)->toBe(4)
        ->and(fn () => Make::page('l6', $parent))->toThrow(InvalidPagePlacementException::class, 'levels');
});

it('refuses a move that would push a descendant past the last level', function (): void {
    $l1 = Make::page('l1');
    $l2 = Make::page('l2', $l1);
    $l3 = Make::page('l3', $l2);
    $l4 = Make::page('l4', $l3);

    $branch = Make::page('branch');
    $leaf = Make::page('leaf', $branch);

    expect(fn () => tree()->move($branch, $l4))->toThrow(InvalidPagePlacementException::class, 'levels')
        ->and($branch->fresh()?->path)->toBe('/branch')
        ->and($leaf->fresh()?->path)->toBe('/branch/leaf');
});

it('refuses an address reserved by a route of the frontend', function (): void {
    config()->set('cms-pages.reserved_segments', ['api', '/auth', '/help/faq']);

    expect(fn () => Make::page('api'))->toThrow(ReservedSegmentException::class, 'reserved')
        ->and(fn () => Make::page('auth'))->toThrow(ReservedSegmentException::class, 'reserved');

    $help = Make::page('help');

    expect(fn () => Make::page('faq', $help))->toThrow(ReservedSegmentException::class, 'reserved')
        ->and(Make::page('authors')->path)->toBe('/authors');
});

it('refuses an address whose first segment is a language code', function (): void {
    expect(fn () => Make::page('en'))->toThrow(ReservedSegmentException::class, 'Accept-Language')
        ->and(fn () => Make::page('de-ch'))->toThrow(ReservedSegmentException::class, 'Accept-Language');

    $info = Make::page('info');

    expect(Make::page('en', $info)->path)->toBe('/info/en');
});

it('refuses a colliding address on create and on move', function (): void {
    Make::page('about');
    $info = Make::page('info');
    $second = Make::page('about', $info);

    expect(fn () => Make::page('about'))->toThrow(PathCollisionException::class)
        ->and(fn () => tree()->move($second, null))->toThrow(PathCollisionException::class)
        ->and($second->fresh()?->path)->toBe('/info/about');
});

it('refuses a slug that is not one kebab segment', function (): void {
    expect(fn () => Make::page('Info Page'))->toThrow(InvalidSlugException::class)
        ->and(fn () => tree()->rename(Make::page('info'), 'info/help'))->toThrow(InvalidSlugException::class);
});

it('takes the address of a singleton from its declaration, not from the tree', function (): void {
    $info = Make::page('info');
    $home = Make::page('home', $info, 'home');

    expect($home->path)->toBe('/')
        ->and($home->parent_id)->toBeNull()
        ->and($home->depth)->toBe(0);
});

it('refuses a second page of a singleton type', function (): void {
    Make::page('home', null, 'home');

    expect(fn () => Make::page('home-again', null, 'home'))
        ->toThrow(PageTypeCardinalityException::class, 'singleton');
});

it('refuses a second active template of a type', function (): void {
    $template = Make::page('concert', null, 'concert');

    expect($template->path)->toBe('/series/{series}/{concert}')
        ->and(fn () => Make::page('concert-two', null, 'concert'))
        ->toThrow(PageTypeCardinalityException::class, 'active template');
});

it('refuses to move a page whose address comes from its type', function (): void {
    $info = Make::page('info');
    $home = Make::page('home', null, 'home');

    expect(fn () => tree()->move($home, $info))->toThrow(InvalidPagePlacementException::class, 'cannot be moved')
        ->and($home->fresh()?->path)->toBe('/');
});

it('refuses a second template of a type even as a draft: the address is one', function (): void {
    Make::page('concert', null, 'concert');

    expect(fn () => tree()->create(new CmsPage([
        'type' => 'concert',
        'slug' => 'concert-draft',
        'name' => ['ru' => 'Черновик'],
        'is_active' => false,
    ])))->toThrow(PageTypeCardinalityException::class)
        ->and(CmsPage::query()->where('type', 'concert')->count())->toBe(1);
});

it('counts a position among the pages of the tree, templates left out', function (): void {
    $first = Make::page('first');
    Make::page('concert', null, 'concert');
    $second = Make::page('second');
    $third = Make::page('third');

    // Position 1 means «after the first visible sibling», whatever a
    // template with the same parent carries in its sort column.
    tree()->move($third, null, 1);

    $visible = CmsPage::query()
        ->whereNull('parent_id')
        ->where('type', 'page')
        ->orderBy('sort_order')
        ->orderBy('id')
        ->pluck('slug')
        ->all();

    expect($visible)->toBe(['first', 'third', 'second'])
        ->and($first->fresh()?->sort_order)->toBeLessThan($third->fresh()?->sort_order ?? 0)
        ->and($third->fresh()?->sort_order)->toBeLessThan($second->fresh()?->sort_order ?? 0);
});

it('refuses to hang a page under a template', function (): void {
    $template = Make::page('concert', null, 'concert');

    expect(fn () => Make::page('child', $template))->toThrow(InvalidPagePlacementException::class, 'template');
});

it('keeps the slug of a fixed address page out of its path', function (): void {
    $home = Make::page('home', null, 'home');

    tree()->rename($home, 'main');

    expect($home->fresh()?->slug)->toBe('main')
        ->and($home->fresh()?->path)->toBe('/');
});

it('sequences the positions of a sibling group', function (): void {
    $first = Make::page('first');
    $second = Make::page('second');
    $third = Make::page('third', position: 0);

    expect(positions())->toBe(['third' => 0, 'first' => 1, 'second' => 2]);

    tree()->move($second, null, 0);

    expect(positions())->toBe(['second' => 0, 'third' => 1, 'first' => 2])
        ->and($first->fresh()?->path)->toBe('/first');
});

/**
 * @return array<string, int>
 */
function positions(): array
{
    return CmsPage::query()
        ->whereNull('parent_id')
        ->orderBy('sort_order')
        ->pluck('sort_order', 'slug')
        ->map(fn (int $order): int => $order)
        ->all();
}

it('reads a subtree by prefix without capturing a sibling of a similar name', function (): void {
    $concert = Make::page('concert');
    $night = Make::page('night', $concert);
    Make::page('concert-hall');

    expect(tree()->descendants($concert)->pluck('path')->all())->toBe(['/concert/night'])
        ->and(tree()->ancestors($night)->pluck('path')->all())->toBe(['/concert']);
});

it('keeps templates out of every subtree', function (): void {
    Make::page('series');
    $template = Make::page('concert', null, 'concert');
    $series = CmsPage::query()->where('slug', 'series')->firstOrFail();

    expect(tree()->descendants($series)->pluck('id')->all())->not->toContain($template->id);
});

it('takes the blocks of a page with it when the page is deleted', function (): void {
    $page = Make::page('info');

    $block = CmsBlock::create([
        'blockable_type' => $page->getMorphClass(),
        'blockable_id' => $page->getKey(),
        'type' => TextBlock::code(),
        'name' => 'Текст',
        'content' => ['body' => ['ru' => '<p>Привет</p>']],
    ]);

    $page->delete();

    // `cms_blocks.blockable_*` is polymorphic and carries no foreign key, so
    // nobody but the owner frees the rows, and the media they hold with them.
    expect($block->fresh())->toBeNull();
});
