<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Http\Payload\PaginationPayload;
use Ewk\Cms\Blocks\Tests\Fixtures\ParameterSets;

it('marks a numbered payload with its mode and hands ready links out', function (): void {
    $set = ParameterSets::pages('raspisanie', ['month' => '2026-01'], page: 1, perPage: 5);
    $payload = PaginationPayload::pages($set, 42, ParameterSets::resolved($set));

    expect($payload)->toBe([
        'mode' => 'pages',
        'per_page' => 5,
        'current_page' => 1,
        'last_page' => 9,
        'total' => 42,
        'has_more' => true,
        'links' => [
            'self' => '?b[raspisanie][month]=2026-01',
            'next' => '?b[raspisanie][month]=2026-01&b[raspisanie][page]=2',
            'prev' => null,
        ],
    ]);
});

it('addresses the first page without a number from the second one', function (): void {
    $set = ParameterSets::pages('raspisanie', page: 2, perPage: 5);
    $links = PaginationPayload::pages($set, 42, ParameterSets::resolved($set))['links'];

    expect($links['self'])->toBe('?b[raspisanie][page]=2')
        ->and($links['prev'])->toBe('?')
        ->and($links['next'])->toBe('?b[raspisanie][page]=3');
});

it('has no continuation on the last page', function (): void {
    $set = ParameterSets::pages('raspisanie', page: 9, perPage: 5);
    $payload = PaginationPayload::pages($set, 42, ParameterSets::resolved($set));

    expect($payload['has_more'])->toBeFalse()
        ->and($payload['links']['next'])->toBeNull()
        ->and($payload['links']['prev'])->toBe('?b[raspisanie][page]=8');
});

it('counts one page for an empty list', function (): void {
    $set = ParameterSets::pages('raspisanie', perPage: 5);
    $payload = PaginationPayload::pages($set, 0, ParameterSets::resolved($set));

    expect($payload['last_page'])->toBe(1)
        ->and($payload['has_more'])->toBeFalse()
        ->and($payload['links']['self'])->toBe('?')
        ->and($payload['links']['next'])->toBeNull();
});

it('reports the actual slice and the address of the next one', function (): void {
    $set = ParameterSets::more('afisha', ['date' => '2026-09-12'], shown: 24, perPage: 12, maxShown: 48);
    $payload = PaginationPayload::more($set, 137, ParameterSets::resolved($set), ['href' => '/events']);

    expect($payload)->toBe([
        'mode' => 'more',
        'continuation' => 'url',
        'per_page' => 12,
        'shown' => 24,
        'total' => 137,
        'has_more' => true,
        'next' => '?b[afisha][date]=2026-09-12&b[afisha][shown]=36',
        'catalog' => ['href' => '/events'],
    ]);
});

it('keeps the continuation and drops the address once the ceiling is reached', function (): void {
    $set = ParameterSets::more('afisha', shown: 48, perPage: 12, maxShown: 48);
    $payload = PaginationPayload::more($set, 137, ParameterSets::resolved($set), ['href' => '/events']);

    expect($payload['shown'])->toBe(48)
        ->and($payload['has_more'])->toBeTrue()
        ->and($payload['next'])->toBeNull()
        ->and($payload['catalog'])->toBe(['href' => '/events']);
});

it('reaches the ceiling in whole portions instead of circling below it', function (): void {
    $before = ParameterSets::more('afisha', shown: 36, perPage: 12, maxShown: 48);
    $at = ParameterSets::more('afisha', shown: 48, perPage: 12, maxShown: 48);

    expect(PaginationPayload::more($before, 1000, ParameterSets::resolved($before))['next'])
        ->toBe('?b[afisha][shown]=48')
        ->and(PaginationPayload::more($at, 1000, ParameterSets::resolved($at))['next'])
        ->toBeNull();
});

it('leaves the catalogue out when the block has none', function (): void {
    $set = ParameterSets::more('afisha', shown: 12, perPage: 12);
    $payload = PaginationPayload::more($set, 12, ParameterSets::resolved($set));

    expect($payload)->not->toHaveKey('catalog')
        ->and($payload['has_more'])->toBeFalse()
        ->and($payload['next'])->toBeNull();
});

it('carries the cursor inside the address of the block endpoint and counts no total', function (): void {
    $payload = PaginationPayload::moreByCursor(12, 'eyJrIjoxfQ', '/api/v1/blocks/9f3c?path=%2F');

    expect($payload)->toBe([
        'mode' => 'more',
        'continuation' => 'endpoint',
        'per_page' => 12,
        'has_more' => true,
        'next' => '/api/v1/blocks/9f3c?path=%2F&cursor=eyJrIjoxfQ',
    ])
        ->and(PaginationPayload::moreByCursor(12, null, '/api/v1/blocks/9f3c'))
        ->toBe([
            'mode' => 'more',
            'continuation' => 'endpoint',
            'per_page' => 12,
            'has_more' => false,
            'next' => null,
        ]);
});

it('describes a list without a continuation by its mode alone', function (): void {
    expect(PaginationPayload::none())->toBe(['mode' => 'none']);
});
