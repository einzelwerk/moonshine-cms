<?php

declare(strict_types=1);

use Ewk\Cms\Blocks\Params\Cursor;

it('survives a round trip through an opaque string', function (): void {
    $cursor = Cursor::encode(['k' => '2026-01-05', 'i' => 42]);

    expect($cursor)->not->toContain('=')
        ->and($cursor)->not->toContain('+')
        ->and($cursor)->not->toContain('/')
        ->and(Cursor::decode($cursor))->toBe(['k' => '2026-01-05', 'i' => 42]);
});

it('gives nothing back for rubbish instead of failing', function (): void {
    expect(Cursor::decode('not a cursor'))->toBeNull()
        ->and(Cursor::decode(''))->toBeNull()
        ->and(Cursor::decode(base64_encode('[1,2,3]')))->toBe(['0' => 1, '1' => 2, '2' => 3])
        ->and(Cursor::decode(base64_encode('"string"')))->toBeNull();
});
