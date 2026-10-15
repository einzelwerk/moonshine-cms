<?php

declare(strict_types=1);

use Ewk\Cms\Seo\Documents\CanonicalUrl;
use Ewk\Cms\Seo\Tests\Fixtures\Make;
use Ewk\Cms\Seo\Tests\Fixtures\SeoOwner;

function canonical(): CanonicalUrl
{
    return app(CanonicalUrl::class);
}

it('points a page without parameters at itself', function (): void {
    $context = Make::context(new SeoOwner(), path: '/afisha');

    expect(canonical()->build($context))->toBe('https://example.com/ru/afisha');
});

it('points a page of pagination at itself and not at the first page', function (): void {
    $context = Make::context(
        new SeoOwner(),
        path: '/afisha',
        query: ['b' => ['afisha' => ['page' => 2]]],
        filterKeys: ['b[afisha][city]'],
    );

    // The brackets stay raw: the address is copied, pasted and lands in a
    // sitemap, and the `self` link of pagination has to match it character for
    // character.
    expect(canonical()->build($context))->toBe('https://example.com/ru/afisha?b[afisha][page]=2');
});

it('drops a filtering parameter and keeps the positional one beside it', function (): void {
    $context = Make::context(
        new SeoOwner(),
        path: '/afisha',
        query: ['b' => ['afisha' => ['city' => 3, 'page' => 2]]],
        filterKeys: ['b[afisha][city]'],
    );

    $url = canonical()->build($context);

    expect($url)->toContain('b[afisha][page]=2')
        ->and($url)->not->toContain('city');
});

it('drops a parent left empty by the removal', function (): void {
    $context = Make::context(
        new SeoOwner(),
        path: '/afisha',
        query: ['b' => ['afisha' => ['city' => 3]]],
        filterKeys: ['b[afisha][city]'],
    );

    expect(canonical()->build($context))->toBe('https://example.com/ru/afisha');
});

it('produces one address for two requests that differ only in the order of parameters', function (): void {
    $first = Make::context(new SeoOwner(), query: ['b' => ['afisha' => ['shown' => 20, 'page' => 2]], 'a' => 1], filterKeys: []);
    $second = Make::context(new SeoOwner(), query: ['a' => 1, 'b' => ['afisha' => ['page' => 2, 'shown' => 20]]], filterKeys: []);

    $address = 'https://example.com/ru/afisha?a=1&b[afisha][page]=2&b[afisha][shown]=20';

    expect(canonical()->build($first))->toBe($address)
        ->and(canonical()->build($second))->toBe($address);
});

it('lets a manual canonical address with a host of its own win as it stands', function (): void {
    $context = Make::context(new SeoOwner(), query: ['b' => ['afisha' => ['page' => 2]]], filterKeys: []);

    expect(canonical()->build($context, 'https://other.test/en/page'))->toBe('https://other.test/en/page')
        ->and(canonical()->build($context, '//other.test/en/page'))->toBe('//other.test/en/page');
});

it('turns a manual canonical address written as a path into an absolute one', function (): void {
    $context = Make::context(new SeoOwner(), locale: 'en', query: ['b' => ['afisha' => ['page' => 2]]], filterKeys: []);

    expect(canonical()->build($context, '/drugaya'))->toBe('https://example.com/en/drugaya')
        ->and(canonical()->build($context, ' /drugaya '))->toBe('https://example.com/en/drugaya');
});

it('ignores a manual canonical address that is blank', function (): void {
    $context = Make::context(new SeoOwner(), query: [], filterKeys: []);

    expect(canonical()->build($context, '   '))->toBe('https://example.com/ru/afisha')
        ->and(canonical()->build($context, null))->toBe('https://example.com/ru/afisha');
});

it('ignores a filter name it cannot parse instead of guessing which parameter to drop', function (): void {
    $context = Make::context(
        new SeoOwner(),
        query: ['b' => ['afisha' => ['city' => 3]]],
        filterKeys: ['b[afisha][', 'b[][city]'],
    );

    expect(canonical()->build($context))->toContain('city');
});

it('builds the same path on another language, prefix aside', function (): void {
    $context = Make::context(new SeoOwner(), path: '/afisha', query: ['b' => ['afisha' => ['page' => 2]]], filterKeys: []);

    expect(canonical()->forLocale($context, 'de'))->toBe('https://example.com/de/afisha?b[afisha][page]=2');
});
