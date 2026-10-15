<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Resolving\PathResolver;
use Ewk\Cms\Pages\Resolving\Resolution;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Support\PageLimits;
use Ewk\Cms\Pages\Tests\Fixtures\Make;
use Ewk\Cms\Pages\Tests\Fixtures\Series;
use Ewk\Cms\Pages\Tests\Fixtures\TestPageTypes;
use Ewk\Cms\Pages\Types\PageTypeRegistry;

beforeEach(function (): void {
    TestPageTypes::registerAll(app(PageTypeRegistry::class));
});

function resolver(): PathResolver
{
    return app(PathResolver::class);
}

it('resolves a page by its stored address', function (): void {
    $info = Make::page('info', attributes: ['layout_code' => 'main']);
    $help = Make::page('help', $info, attributes: ['layout_code' => 'main']);

    $resolved = resolver()->resolve('/info/help');

    expect($resolved->found)->toBeTrue()
        ->and($resolved->outcome)->toBe(Resolution::PAGE)
        ->and($resolved->page?->id)->toBe($help->id)
        ->and($resolved->type?->key())->toBe('page')
        ->and($resolved->subject)->toBeNull()
        ->and($resolved->layoutCode())->toBe('main');
});

it('resolves the same page through every shape of the same address', function (string $raw): void {
    Make::page('info');

    $resolved = resolver()->resolve($raw);

    expect($resolved->found)->toBeTrue()
        ->and($resolved->path)->toBe('/info');
})->with(['/info', 'info', '/info/', '//info//', '/inf%6F']);

it('resolves the singleton at the root', function (): void {
    Make::page('home', null, 'home');

    $resolved = resolver()->resolve('/');

    expect($resolved->found)->toBeTrue()
        ->and($resolved->type?->key())->toBe('home');
});

it('reports a language prefix as its own outcome, not as a miss', function (string $raw, string $language): void {
    Make::page('info');

    $resolved = resolver()->resolve($raw);

    expect($resolved->outcome)->toBe(Resolution::LANGUAGE_PREFIX)
        ->and($resolved->found)->toBeFalse()
        ->and($resolved->language)->toBe($language)
        ->and($resolved->page)->toBeNull();
})->with([
    'catalog language' => ['/en/info', 'en'],
    'default language' => ['/ru/info', 'ru'],
    'regional code of a catalog language' => ['/de-CH/info', 'de-CH'],
    'primary subtag of a regional catalog language' => ['/de/info', 'de'],
]);

it('does not serve an inactive page', function (): void {
    $info = Make::page('info');
    $info->update(['is_active' => false]);

    expect(resolver()->resolve('/info')->outcome)->toBe(Resolution::NOT_FOUND);
});

it('resolves an entity through the path template of its type', function (): void {
    $template = Make::page('concert', null, 'concert', ['layout_code' => 'main']);
    $concert = Make::concert('winter', 'night-show');

    $resolved = resolver()->resolve('/series/winter/night-show');

    expect($resolved->found)->toBeTrue()
        ->and($resolved->outcome)->toBe(Resolution::SUBJECT)
        ->and($resolved->subject?->getKey())->toBe($concert->id)
        ->and($resolved->type?->key())->toBe('concert')
        ->and($resolved->page?->id)->toBe($template->id)
        ->and($resolved->layoutCode())->toBe('main');
});

it('hides an entity that fails the visibility criterion of its type', function (): void {
    Make::page('concert', null, 'concert');
    Make::concert('winter', 'night-show', hidden: true);

    $resolved = resolver()->resolve('/series/winter/night-show');

    expect($resolved->found)->toBeFalse()
        ->and($resolved->outcome)->toBe(Resolution::NOT_FOUND)
        ->and($resolved->subject)->toBeNull();
});

it('refuses an entity reached through the wrong context segment', function (): void {
    Make::page('concert', null, 'concert');
    Make::concert('winter', 'night-show');
    Series::query()->create(['slug' => 'summer']);

    expect(resolver()->resolve('/series/summer/night-show')->found)->toBeFalse()
        ->and(resolver()->resolve('/series/winter/night-show')->found)->toBeTrue();
});

it('does not open a template by its own stored address', function (): void {
    Make::page('concert', null, 'concert');

    $resolved = resolver()->resolve('/series/{series}/{concert}');

    expect($resolved->found)->toBeFalse()
        ->and($resolved->outcome)->toBe(Resolution::NOT_FOUND);
});

it('misses an entity whose template has not been built', function (): void {
    Make::concert('winter', 'night-show');

    expect(resolver()->resolve('/series/winter/night-show')->found)->toBeFalse();
});

it('misses an unknown address', function (string $raw): void {
    Make::page('info');
    Make::page('concert', null, 'concert');

    $resolved = resolver()->resolve($raw);

    expect($resolved->found)->toBeFalse()
        ->and($resolved->outcome)->toBe(Resolution::NOT_FOUND)
        ->and($resolved->page)->toBeNull()
        ->and($resolved->subject)->toBeNull();
})->with([
    'no such page' => ['/nowhere'],
    'no such entity' => ['/series/winter/night-show'],
    'a shape no template describes' => ['/series/winter'],
    'longer than the column that stores it' => ['/'.str_repeat('a', PageLimits::PATH_LENGTH)],
]);

it('stops serving the old address after a move', function (): void {
    $info = Make::page('info');
    $help = Make::page('help', $info);
    $about = Make::page('about');

    app(PageTree::class)->move($help, $about);

    expect(resolver()->resolve('/info/help')->found)->toBeFalse()
        ->and(resolver()->resolve('/about/help')->page?->id)->toBe($help->id);
});
