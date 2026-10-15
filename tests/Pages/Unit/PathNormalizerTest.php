<?php

declare(strict_types=1);

use Ewk\Cms\Pages\Support\PageLimits;
use Ewk\Cms\Pages\Support\PathNormalizer;

it('normalizes an address the one way everything compares addresses', function (string $raw, string $expected): void {
    expect(PathNormalizer::normalize($raw))->toBe($expected);
})->with([
    'adds the leading slash' => ['info/help', '/info/help'],
    'drops the trailing slash' => ['/info/help/', '/info/help'],
    'collapses repeated slashes' => ['//info///help', '/info/help'],
    'expands percent encoding' => ['/info/%D0%BF%D0%BE%D0%BC%D0%BE%D1%89%D1%8C', '/info/помощь'],
    'keeps the case' => ['/Info/Help', '/Info/Help'],
    'empty address is the root' => ['', '/'],
    'a lone slash is the root' => ['/', '/'],
    'surrounding blanks are not part of the address' => ['  /info/help  ', '/info/help'],
]);

it('splits an address into segments and the root into none', function (): void {
    expect(PathNormalizer::segments('/tours/stand-up/night'))->toBe(['tours', 'stand-up', 'night'])
        ->and(PathNormalizer::segments('/'))->toBe([]);
});

it('joins a child address onto its parent', function (): void {
    expect(PathNormalizer::join('/info', 'help'))->toBe('/info/help')
        ->and(PathNormalizer::join(null, 'info'))->toBe('/info')
        ->and(PathNormalizer::join('/', 'info'))->toBe('/info');
});

it('reports the length limit instead of truncating a different address', function (): void {
    $long = '/'.str_repeat('a', PageLimits::PATH_LENGTH);

    expect(PathNormalizer::withinLimit($long))->toBeFalse()
        ->and(PathNormalizer::normalize($long))->toBe($long)
        ->and(PathNormalizer::withinLimit('/'.str_repeat('a', PageLimits::PATH_LENGTH - 1)))->toBeTrue();
});

it('compares prefixes without capturing a sibling', function (): void {
    expect(PathNormalizer::isDescendantOf('/concert/night', '/concert'))->toBeTrue()
        ->and(PathNormalizer::isDescendantOf('/concert-hall', '/concert'))->toBeFalse()
        ->and(PathNormalizer::isDescendantOf('/concert', '/concert'))->toBeFalse()
        ->and(PathNormalizer::isDescendantOf('/anything', '/'))->toBeTrue();
});
