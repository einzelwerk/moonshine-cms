<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Params\PageQuery;
use Ewk\Cms\Blocks\Tests\Fixtures\ParameterSets;

it('writes only what differs from the declaration, with sorted keys', function (): void {
    $resolved = ParameterSets::resolved(
        ParameterSets::pages('raspisanie', ['month' => '2026-01', 'city' => 'berlin'], page: 2),
        ParameterSets::pages('afisha'),
    );

    expect($resolved->toQuery())->toBe([
        'b' => ['raspisanie' => ['city' => 'berlin', 'month' => '2026-01', 'page' => 2]],
    ]);
});

it('gives the same query for the same state whatever the order of the sets', function (): void {
    $one = ParameterSets::resolved(
        ParameterSets::pages('zebra', ['city' => 'berlin'], page: 3),
        ParameterSets::pages('afisha', ['month' => '2026-01']),
    );

    $other = ParameterSets::resolved(
        ParameterSets::pages('afisha', ['month' => '2026-01']),
        ParameterSets::pages('zebra', ['city' => 'berlin'], page: 3),
    );

    expect($one->toQuery())->toBe($other->toQuery())
        ->and(array_keys($one->toQuery()['b']))->toBe(['afisha', 'zebra']);
});

it('leaves the first page and the first slice out of the address', function (): void {
    $resolved = ParameterSets::resolved(
        ParameterSets::pages('afisha', page: 1),
        ParameterSets::more('lenta', shown: 12, perPage: 12),
    );

    expect($resolved->toQuery())->toBe([]);
});

it('names the filtering keys of every block of the page', function (): void {
    $resolved = ParameterSets::resolved(
        ParameterSets::pages('afisha'),
        ParameterSets::more('lenta'),
    );

    expect($resolved->filterQueryKeys())
        ->toBe(['b[afisha][city]', 'b[afisha][month]', 'b[lenta][date]']);
});

it('carries a portion size the visitor asked for into the address', function (): void {
    $resolved = ParameterSets::resolved(
        ParameterSets::pages('afisha', page: 2, perPage: 20, defaultPerPage: 5),
    );

    expect($resolved->toQuery())
        ->toBe(['b' => ['afisha' => ['page' => 2, 'per_page' => 20]]])
        ->and(PageQuery::with($resolved, 'afisha', ['page' => null]))
        ->toBe('?b[afisha][per_page]=20');
});

it('leaves the portion size out while it is the one in force', function (): void {
    $resolved = ParameterSets::resolved(ParameterSets::pages('afisha', perPage: 5, defaultPerPage: 5));

    expect($resolved->toQuery())->toBe([]);
});

it('keeps the brackets of a value escaped and the ones of the namespace readable', function (): void {
    $resolved = ParameterSets::resolved(ParameterSets::pages('afisha', ['month' => 'a[b] c&d']));

    expect(PageQuery::with($resolved, 'afisha'))
        ->toBe('?b[afisha][month]=a%5Bb%5D%20c%26d');
});

it('finds a set by anchor and nothing under a foreign one', function (): void {
    $resolved = ParameterSets::resolved(ParameterSets::pages('afisha'));

    expect($resolved->for('afisha')?->anchor)->toBe('afisha')
        ->and($resolved->for('raspisanie'))->toBeNull()
        ->and($resolved->anchors())->toBe(['afisha']);
});

it('overrides one set of the page and keeps the others untouched', function (): void {
    $resolved = ParameterSets::resolved(
        ParameterSets::pages('afisha', ['city' => 'berlin'], page: 2),
        ParameterSets::more('lenta', ['date' => '2026-09-12'], shown: 24),
    );

    expect(PageQuery::with($resolved, 'afisha', ['page' => 3]))
        ->toBe('?b[afisha][city]=berlin&b[afisha][page]=3&b[lenta][date]=2026-09-12&b[lenta][shown]=24')
        ->and(PageQuery::with($resolved, 'afisha', ['page' => null]))
        ->toBe('?b[afisha][city]=berlin&b[lenta][date]=2026-09-12&b[lenta][shown]=24');
});

it('gives a bare question mark when the address carries nothing', function (): void {
    $resolved = ParameterSets::resolved(ParameterSets::pages('afisha', page: 2));

    // Not an empty string: an empty href points at the current address with
    // the whole of its query string, a bare `?` drops it.
    expect(PageQuery::with($resolved, 'afisha', ['page' => null]))->toBe('?')
        ->and(PageQuery::with($resolved, 'afisha'))->toBe('?b[afisha][page]=2');
});
