<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Params\PageQuery;

it('sorts the keys of every level and writes no leading question mark', function (): void {
    expect(PageQuery::encode([
        'page' => 2,
        'b' => [
            'raspisanie' => ['page' => 2, 'month' => '2026-01'],
            'afisha' => ['shown' => 36],
        ],
        'a' => 'first',
    ]))->toBe('a=first&b[afisha][shown]=36&b[raspisanie][month]=2026-01&b[raspisanie][page]=2&page=2');
});

it('leaves the brackets of a key readable and escapes the ones of a value', function (): void {
    expect(PageQuery::encode(['b' => ['afisha' => ['month' => 'a[b]']]]))
        ->toBe('b[afisha][month]=a%5Bb%5D')
        ->and(PageQuery::encode(['b' => ['afisha' => ['q' => 'море & солнце']]]))
        ->toBe('b[afisha][q]=%D0%BC%D0%BE%D1%80%D0%B5%20%26%20%D1%81%D0%BE%D0%BB%D0%BD%D1%86%D0%B5');
});

it('writes a boolean the way a query string spells it and skips nothing else', function (): void {
    expect(PageQuery::encode(['past' => true, 'free' => false, 'radius' => 10.5]))
        ->toBe('free=0&past=1&radius=10.5');
});

it('gives an empty string for an empty query and for empty nested sets', function (): void {
    expect(PageQuery::encode([]))->toBe('')
        ->and(PageQuery::encode(['b' => []]))->toBe('');
});
