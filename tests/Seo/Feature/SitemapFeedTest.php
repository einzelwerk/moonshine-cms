<?php

declare(strict_types=1);

use Ewk\Cms\Seo\Sitemap\SitemapItem;
use Ewk\Cms\Seo\Sitemap\SitemapRegistry;
use Ewk\Cms\Seo\Sitemap\SitemapSource;
use Ewk\Cms\Seo\Tests\Fixtures\Make;
use Ewk\Cms\Seo\Tests\Fixtures\Sitemap\SitemapWorld;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    config()->set('cms-media.locales', ['ru', 'en', 'de']);
    config()->set('cms-media.default_locale', 'ru');

    SitemapWorld::build();
});

/**
 * @return list<string>
 */
function sitemapFiles(): array
{
    return array_column(test()->getJson('/api/v1/sitemap')->assertOk()->json('data'), 'id');
}

/**
 * @return list<array<string, mixed>>
 */
function sitemapFile(string $id): array
{
    return test()->getJson('/api/v1/sitemap/'.$id)->assertOk()->json('data');
}

it('names one file per language and source, and none for a source with nothing to list', function (): void {
    SitemapWorld::page('/', 'home');
    SitemapWorld::page('/contacts');

    expect(sitemapFiles())->toBe(['ru-pages-1', 'en-pages-1', 'de-pages-1']);
});

it('answers absolute addresses with every language version, itself and x-default included', function (): void {
    SitemapWorld::page('/contacts', publishedAt: new Carbon('2026-09-14 10:00:00'));

    expect(sitemapFile('en-pages-1'))->toBe([
        [
            'url' => 'https://example.com/en/contacts',
            'lastModified' => '2026-09-14T10:00:00+00:00',
            'alternates' => [
                'languages' => [
                    'ru' => 'https://example.com/ru/contacts',
                    'en' => 'https://example.com/en/contacts',
                    'de' => 'https://example.com/de/contacts',
                    'x-default' => 'https://example.com/ru/contacts',
                ],
            ],
        ],
    ]);
});

it('writes the root of a language without a trailing slash', function (): void {
    SitemapWorld::page('/', 'home');

    expect(sitemapFile('de-pages-1')[0]['url'])->toBe('https://example.com/de');
});

it('lists a single language site without a language in the names and without alternates', function (): void {
    config()->set('cms-media.locales', ['ru']);
    SitemapWorld::page('/contacts');

    expect(sitemapFiles())->toBe(['pages-1'])
        ->and(sitemapFile('pages-1'))->toBe([
            ['url' => 'https://example.com/ru/contacts', 'lastModified' => '2026-09-14T10:00:00+00:00'],
        ]);
});

it('lists one entry per path when addresses carry no language', function (): void {
    config()->set('cms-seo.locale_prefix', 'never');
    SitemapWorld::page('/contacts');

    expect(sitemapFiles())->toBe(['pages-1'])
        ->and(sitemapFile('pages-1'))->toBe([
            ['url' => 'https://example.com/contacts', 'lastModified' => '2026-09-14T10:00:00+00:00'],
        ]);
});

it('leaves out a draft, a page switched off and the template itself', function (): void {
    SitemapWorld::page('/contacts');
    SitemapWorld::page('/draft', publishedAt: null);
    SitemapWorld::page('/off', active: false);
    SitemapWorld::template();

    expect(array_column(sitemapFile('ru-pages-1'), 'url'))->toBe(['https://example.com/ru/contacts']);
});

it('leaves out what an editor took out of the index or pointed elsewhere', function (): void {
    SitemapWorld::page('/contacts');
    Make::metadata(SitemapWorld::page('/hidden'), ['robots_index' => false]);
    Make::metadata(SitemapWorld::page('/copy'), ['canonical' => '/contacts']);
    Make::metadata(SitemapWorld::page('/blank'), ['canonical' => '  ']);
    Make::metadata(SitemapWorld::page('/nofollow'), ['robots_follow' => false]);

    expect(array_column(sitemapFile('ru-pages-1'), 'url'))->toBe([
        'https://example.com/ru/contacts',
        'https://example.com/ru/blank',
        'https://example.com/ru/nofollow',
    ]);
});

it('dates a page by its publication, or by a later edit of its metadata', function (): void {
    $page = SitemapWorld::page('/contacts', publishedAt: new Carbon('2026-09-14 10:00:00'));

    $metadata = Make::metadata($page);
    $metadata->timestamps = false;
    $metadata->updated_at = new Carbon('2026-10-02 08:30:00');
    $metadata->save();

    expect(sitemapFile('ru-pages-1')[0]['lastModified'])->toBe('2026-10-02T08:30:00+00:00');
});

it('lists the entities of a template type at the address its declaration builds', function (): void {
    SitemapWorld::template();
    SitemapWorld::concert('opening', 'jazz');
    SitemapWorld::concert('closed', 'jazz', ['is_active' => false]);

    expect(sitemapFiles())->toBe(['ru-concert-1', 'en-concert-1', 'de-concert-1'])
        ->and(array_column(sitemapFile('ru-concert-1'), 'url'))->toBe(['https://example.com/ru/series/jazz/opening']);
});

it('lists no entity of a type whose template is not published', function (): void {
    SitemapWorld::concert('opening');

    SitemapWorld::template(publishedAt: null);
    expect(sitemapFiles())->toBe([]);

    test()->getJson('/api/v1/sitemap/ru-concert-1')->assertNotFound();
});

it('dates an entity by the latest of its edit, its metadata and the publication of the template', function (): void {
    SitemapWorld::template(new Carbon('2026-09-01 10:00:00'));
    $concert = SitemapWorld::concert('opening', updatedAt: new Carbon('2026-09-20 12:00:00'));

    expect(sitemapFile('ru-concert-1')[0]['lastModified'])->toBe('2026-09-20T12:00:00+00:00');

    $metadata = Make::metadata($concert);
    $metadata->timestamps = false;
    $metadata->updated_at = new Carbon('2026-10-05 09:00:00');
    $metadata->save();

    expect(sitemapFile('ru-concert-1')[0]['lastModified'])->toBe('2026-10-05T09:00:00+00:00');
});

it('lets the installation narrow a type to what should be listed', function (): void {
    SitemapWorld::template();
    SitemapWorld::concert('opening');
    SitemapWorld::concert('secret', attributes: ['is_hidden' => true]);

    app(SitemapRegistry::class)->narrow('concert', static function (Builder $query): void {
        $query->where('is_hidden', false);
    });

    expect(array_column(sitemapFile('ru-concert-1'), 'url'))->toBe(['https://example.com/ru/series/jazz/opening']);
});

it('leaves out an entity an editor took out of the index', function (): void {
    SitemapWorld::template();
    SitemapWorld::concert('opening');
    Make::metadata(SitemapWorld::concert('private'), ['robots_index' => false]);

    expect(array_column(sitemapFile('ru-concert-1'), 'url'))->toBe(['https://example.com/ru/series/jazz/opening']);
});

it('cuts a source into files of the configured size', function (): void {
    config()->set('cms-media.locales', ['ru']);
    config()->set('cms-seo.sitemap.per_file', 2);

    SitemapWorld::page('/a');
    SitemapWorld::page('/b');
    SitemapWorld::page('/c');

    expect(sitemapFiles())->toBe(['pages-1', 'pages-2'])
        ->and(array_column(sitemapFile('pages-1'), 'url'))->toBe(['https://example.com/ru/a', 'https://example.com/ru/b'])
        ->and(array_column(sitemapFile('pages-2'), 'url'))->toBe(['https://example.com/ru/c']);
});

it('answers 404 for a file the feed does not have', function (string $id): void {
    SitemapWorld::page('/contacts');

    test()->getJson('/api/v1/sitemap/'.$id)
        ->assertNotFound()
        ->assertJsonPath('message', __('cms-seo::seo.sitemap.not_found'))
        ->assertJsonPath('code', 'not_found');
})->with([
    'a part past the end' => 'ru-pages-2',
    'an unknown source' => 'ru-tours-1',
    'an unknown language' => 'fr-pages-1',
    'no language on a multilingual site' => 'pages-1',
    'no part' => 'ru-pages',
    'part zero' => 'ru-pages-0',
]);

it('lists the sources of the installation after the derived ones', function (): void {
    SitemapWorld::page('/contacts');

    app(SitemapRegistry::class)->register(new class () implements SitemapSource {
        public function key(): string
        {
            return 'landing';
        }

        public function count(): int
        {
            return 1;
        }

        public function items(int $offset, int $limit): array
        {
            return [new SitemapItem('/landing')];
        }
    });

    expect(sitemapFiles())->toBe(['ru-pages-1', 'ru-landing-1', 'en-pages-1', 'en-landing-1', 'de-pages-1', 'de-landing-1'])
        ->and(sitemapFile('en-landing-1')[0])->not->toHaveKey('lastModified');
});

it('refuses two sources under one key', function (): void {
    $registry = app(SitemapRegistry::class);

    $registry->register(new class () implements SitemapSource {
        public function key(): string
        {
            return 'pages';
        }

        public function count(): int
        {
            return 0;
        }

        public function items(int $offset, int $limit): array
        {
            return [];
        }
    });

    $registry->sources();
})->throws(InvalidArgumentException::class);

it('does not depend on the language of the request', function (): void {
    SitemapWorld::page('/contacts');

    test()->getJson('/api/v1/sitemap', ['Accept-Language' => 'de'])
        ->assertOk()
        ->assertHeaderMissing('Vary');
});
