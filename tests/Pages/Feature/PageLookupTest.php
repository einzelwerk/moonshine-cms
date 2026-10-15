<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Tests\Fixtures\Blocks\ListingBlock;
use Ewk\Cms\Pages\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/*
 * The pages links point at are read once per request, see PageLookup: a menu
 * of forty links to a handful of pages is not forty reads of `cms_pages`, and
 * the answer of every link is the one it was when each link read its own row.
 */

beforeEach(function (): void {
    TestPageTypes::registerAll(app(PageTypeRegistry::class));
});

/**
 * The statements against one table run by the callback.
 *
 * @return list<string>
 */
function lookupQueriesOn(string $table, callable $call): array
{
    $queries = [];

    DB::listen(static function (QueryExecuted $query) use (&$queries, $table): void {
        if (str_contains($query->sql, '"'.$table.'"')) {
            $queries[] = $query->sql;
        }
    });

    $call();

    // A listener cannot be removed one by one; the next test boots anew.
    return $queries;
}

function lookupHref(Link $link): ?string
{
    return app(LinkResolverRegistry::class)->resolve($link, 'ru')->href;
}

function lookupListing(CmsPage $page, string $anchor): void
{
    $page->blocks()->create([
        'type' => ListingBlock::code(),
        'name' => 'Список '.$anchor,
        'anchor' => $anchor,
        'content' => [],
        'is_active' => true,
    ]);
}

it('reads the pages of a whole menu in one query, whatever their state', function (): void {
    $pages = [];

    foreach (['agb', 'impressum', 'kontakt', 'faq', 'about'] as $slug) {
        $pages[] = Make::page($slug);
    }

    $off = Make::page('archiv', attributes: ['is_active' => false]);
    $gone = '3f1d2c4e-0000-4000-8000-000000000000';

    $links = [
        ...array_map(static fn (CmsPage $page): Link => new Link(LinkKind::PAGE, ['page' => $page->uuid]), $pages),
        new Link(LinkKind::PAGE, ['page' => $off->uuid]),
        new Link(LinkKind::PAGE, ['page' => $gone]),
        // The same page twice, and a link of another kind into it.
        new Link(LinkKind::PAGE, ['page' => $pages[0]->uuid]),
        new Link(LinkKind::LISTING, ['page' => $pages[1]->uuid]),
    ];

    $hrefs = [];
    $queries = lookupQueriesOn('cms_pages', function () use ($links, &$hrefs): void {
        app(LinkResolverRegistry::class)->preload($links);

        foreach ($links as $link) {
            $hrefs[] = lookupHref($link);
        }
    });

    expect($queries)->toHaveCount(1)
        ->and($hrefs)->toBe(['/agb', '/impressum', '/kontakt', '/faq', '/about', null, null, '/agb', '/impressum']);
});

it('reads a page once however many links point at it, without a preload too', function (): void {
    $page = Make::page('agb');
    $gone = '3f1d2c4e-0000-4000-8000-000000000000';

    $hrefs = [];
    $queries = lookupQueriesOn('cms_pages', function () use ($page, $gone, &$hrefs): void {
        foreach (range(1, 4) as $ignored) {
            $hrefs[] = lookupHref(new Link(LinkKind::PAGE, ['page' => $page->uuid]));
            $hrefs[] = lookupHref(new Link(LinkKind::PAGE, ['page' => $gone]));
        }
    });

    expect($queries)->toHaveCount(2)
        ->and(array_unique($hrefs))->toBe(['/agb', null]);
});

it('reads the blocks of a catalog once for every link with filters into it', function (): void {
    $catalog = Make::page('events');
    lookupListing($catalog, 'afisha');

    $hrefs = [];
    $queries = lookupQueriesOn('cms_blocks', function () use ($catalog, &$hrefs): void {
        foreach (['concert', 'theater', 'ballet', 'sport'] as $category) {
            $hrefs[] = lookupHref(new Link(LinkKind::LISTING, ['page' => $catalog->uuid, 'filters' => ['city' => $category]]));
        }
    });

    expect($queries)->toHaveCount(1)
        ->and($hrefs)->toBe([
            '/events?b[afisha][city]=concert',
            '/events?b[afisha][city]=theater',
            '/events?b[afisha][city]=ballet',
            '/events?b[afisha][city]=sport',
        ]);
});

it('answers from the database again once a page is written in the same request', function (): void {
    $page = Make::page('agb');
    $catalog = Make::page('events');
    lookupListing($catalog, 'afisha');

    $link = new Link(LinkKind::PAGE, ['page' => $page->uuid]);
    $listing = new Link(LinkKind::LISTING, ['page' => $catalog->uuid, 'filters' => ['city' => 'praha']]);

    $before = [lookupHref($link), lookupHref($listing)];

    // Renamed, then switched off: each write is seen by the very next link.
    $page->update(['path' => '/terms']);
    $renamed = lookupHref($link);

    $page->update(['is_active' => false]);
    $switchedOff = lookupHref($link);

    // A block of the catalog switched off leaves the filters nowhere to land.
    $catalog->blocks()->firstOrFail()->update(['is_active' => false]);
    $noList = lookupHref($listing);

    $catalog->delete();
    $deleted = lookupHref(new Link(LinkKind::LISTING, ['page' => $catalog->uuid]));

    expect($before)->toBe(['/agb', '/events?b[afisha][city]=praha'])
        ->and($renamed)->toBe('/terms')
        ->and($switchedOff)->toBeNull()
        ->and($noList)->toBeNull()
        ->and($deleted)->toBeNull();
});

it('keeps nothing beyond the scope of the request', function (): void {
    $page = Make::page('agb');
    $link = new Link(LinkKind::PAGE, ['page' => $page->uuid]);

    lookupHref($link);

    // A write that bypasses the model fires no event: within the request the
    // lookup still answers what it read, the next request reads again, the
    // way Octane starts every request with fresh scoped instances.
    DB::table('cms_pages')->where('id', $page->id)->update(['is_active' => false]);

    $sameRequest = null;
    $within = lookupQueriesOn('cms_pages', function () use ($link, &$sameRequest): void {
        $sameRequest = lookupHref($link);
    });

    app()->forgetScopedInstances();

    $nextRequest = 'unset';
    $next = lookupQueriesOn('cms_pages', function () use ($link, &$nextRequest): void {
        $nextRequest = lookupHref($link);
    });

    expect($within)->toBe([])
        ->and($sameRequest)->toBe('/agb')
        ->and($next)->toHaveCount(1)
        ->and($nextRequest)->toBeNull();
});
